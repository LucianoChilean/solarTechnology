<?php
/**
 * Plugin Name: SolarTech · 40 Monitor de seguridad
 * Description: Panel "Seguridad" en wp-admin: estado de salud, lista negra de IP, registro de visitas y detección/auto-bloqueo de ataques (SQLi, XSS, path traversal, RCE, escáneres).
 * Version: 1.0.0
 * Author: SolarTechonolgy
 *
 * @package SolarTechSecurity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ST_Monitor {

	const OPT_BLACKLIST = 'st_ip_blacklist';
	const OPT_VISITS    = 'st_visit_log';
	const OPT_ATTACKS   = 'st_attack_log';
	const OPT_STRIKES   = 'st_ip_strikes';
	const MAX_VISITS    = 200;
	const MAX_ATTACKS   = 200;
	const STRIKE_LIMIT  = 4; // auto-bloqueo tras N ataques.

	public static function init() {
		add_action( 'init', array( __CLASS__, 'guard' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_st_add_ip', array( __CLASS__, 'handle_add_ip' ) );
		add_action( 'admin_post_st_del_ip', array( __CLASS__, 'handle_del_ip' ) );
		// Integración con el throttle de login.
		add_action( 'st_login_lockout', array( __CLASS__, 'blacklist_ip' ) );
	}

	/* ---- IP helper ------------------------------------------------------- */
	protected static function ip() {
		return function_exists( 'st_client_ip' ) ? st_client_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
	}

	/* ---- Guardia en cada request ---------------------------------------- */
	public static function guard() {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		$ip = self::ip();

		// 1) IP en lista negra → 403.
		$black = (array) get_option( self::OPT_BLACKLIST, array() );
		if ( in_array( $ip, $black, true ) ) {
			status_header( 403 );
			nocache_headers();
			wp_die( 'Acceso denegado.', 'Bloqueado', array( 'response' => 403 ) );
		}

		// 2) Detección de ataques en la URL + query.
		$signature = self::detect_attack();
		if ( $signature ) {
			self::log_attack( $ip, $signature );
			$strikes = (array) get_option( self::OPT_STRIKES, array() );
			$strikes[ $ip ] = ( $strikes[ $ip ] ?? 0 ) + 1;
			update_option( self::OPT_STRIKES, $strikes, false );
			if ( $strikes[ $ip ] >= self::STRIKE_LIMIT ) {
				self::blacklist_ip( $ip );
			}
			status_header( 403 );
			nocache_headers();
			wp_die( 'Petición bloqueada por el sistema de seguridad.', 'Bloqueado', array( 'response' => 403 ) );
		}

		// 3) Registro de visita (solo front, no admin/ajax).
		if ( ! is_admin() && ! wp_doing_ajax() ) {
			self::log_visit( $ip );
		}
	}

	/* ---- Detección de patrones ofensivos -------------------------------- */
	protected static function detect_attack() {
		$uri   = rawurldecode( $_SERVER['REQUEST_URI'] ?? '' );
		$query = rawurldecode( $_SERVER['QUERY_STRING'] ?? '' );
		$ua    = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$hay   = strtolower( $uri . ' ' . $query );

		$patterns = array(
			'SQLi'            => '/(\bunion\b.+\bselect\b|\bselect\b.+\bfrom\b|information_schema|\bor\b\s+1=1|sleep\(\s*\d|benchmark\(|concat\(|load_file\()/i',
			'XSS'             => '/(<script|onerror\s*=|onload\s*=|javascript:|document\.cookie|<iframe|%3cscript)/i',
			'Path traversal'  => '#(\.\./|\.\.\\\\|/etc/passwd|/proc/self|c:\\\\windows)#i',
			'Acceso a config' => '/(wp-config\.php|\.env|\.git/|\.htaccess|composer\.(json|lock)|id_rsa)/i',
			'RCE'             => '/(;\s*(cat|wget|curl|bash|sh|nc|python|perl)\b|\bsystem\(|\bexec\(|passthru\(|shell_exec\(|base64_decode\()/i',
			'Escaneo'         => '/(\/vendor\/phpunit|eval-stdin\.php|\.\/php:\/\/input|allow_url_include)/i',
		);
		foreach ( $patterns as $label => $re ) {
			if ( preg_match( $re, $hay ) ) {
				return $label;
			}
		}

		// Herramientas ofensivas por User-Agent.
		$tools = array( 'sqlmap', 'nikto', 'wpscan', 'nmap', 'masscan', 'dirbuster', 'gobuster', 'hydra', 'acunetix', 'nessus', 'zgrab', 'fimap', 'w3af' );
		foreach ( $tools as $t ) {
			if ( $ua && strpos( $ua, $t ) !== false ) {
				return 'Herramienta: ' . $t;
			}
		}
		return '';
	}

	/* ---- Registros ------------------------------------------------------- */
	protected static function log_visit( $ip ) {
		$log = (array) get_option( self::OPT_VISITS, array() );
		$log[] = array(
			'ip'   => $ip,
			'uri'  => substr( sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' ), 0, 180 ),
			'ua'   => substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 160 ),
			'time' => time(),
		);
		if ( count( $log ) > self::MAX_VISITS ) {
			$log = array_slice( $log, -self::MAX_VISITS );
		}
		update_option( self::OPT_VISITS, $log, false );
	}

	protected static function log_attack( $ip, $sig ) {
		$log = (array) get_option( self::OPT_ATTACKS, array() );
		$log[] = array(
			'ip'   => $ip,
			'sig'  => $sig,
			'uri'  => substr( sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' ), 0, 200 ),
			'time' => time(),
		);
		if ( count( $log ) > self::MAX_ATTACKS ) {
			$log = array_slice( $log, -self::MAX_ATTACKS );
		}
		update_option( self::OPT_ATTACKS, $log, false );
	}

	/* ---- Lista negra ----------------------------------------------------- */
	public static function blacklist_ip( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return;
		}
		$black = (array) get_option( self::OPT_BLACKLIST, array() );
		if ( ! in_array( $ip, $black, true ) ) {
			$black[] = $ip;
			update_option( self::OPT_BLACKLIST, $black, false );
		}
	}

	/* ---- Panel de admin -------------------------------------------------- */
	public static function menu() {
		add_menu_page(
			'Seguridad',
			'Seguridad',
			'manage_options',
			'st-seguridad',
			array( __CLASS__, 'render' ),
			'dashicons-shield-alt',
			58
		);
	}

	public static function handle_add_ip() {
		check_admin_referer( 'st_ip' );
		if ( current_user_can( 'manage_options' ) ) {
			$ip = sanitize_text_field( wp_unslash( $_POST['ip'] ?? '' ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				self::blacklist_ip( $ip );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=st-seguridad' ) );
		exit;
	}

	public static function handle_del_ip() {
		check_admin_referer( 'st_ip' );
		if ( current_user_can( 'manage_options' ) ) {
			$ip    = sanitize_text_field( wp_unslash( $_POST['ip'] ?? '' ) );
			$black = array_values( array_diff( (array) get_option( self::OPT_BLACKLIST, array() ), array( $ip ) ) );
			update_option( self::OPT_BLACKLIST, $black, false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=st-seguridad' ) );
		exit;
	}

	protected static function health() {
		global $wp_version, $wpdb;
		$rows = array();
		$rows[] = array( 'PHP ' . PHP_VERSION, version_compare( PHP_VERSION, '8.1', '>=' ) );
		$rows[] = array( 'WordPress ' . $wp_version, true );
		$rows[] = array( 'HTTPS activo', is_ssl() );
		$rows[] = array( 'Edición de archivos deshabilitada (DISALLOW_FILE_EDIT)', defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT );
		$rows[] = array( 'XML-RPC deshabilitado', ! apply_filters( 'xmlrpc_enabled', false ) );
		$rows[] = array( 'Actualizaciones automáticas (core minor)', ! ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) );
		$rows[] = array( 'Conexión a la base de datos', (bool) $wpdb->db_version() );
		$rows[] = array( 'reCAPTCHA configurado', function_exists( 'st_recaptcha_enabled' ) && st_recaptcha_enabled() );
		return $rows;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$black   = (array) get_option( self::OPT_BLACKLIST, array() );
		$visits  = array_reverse( (array) get_option( self::OPT_VISITS, array() ) );
		$attacks = array_reverse( (array) get_option( self::OPT_ATTACKS, array() ) );
		?>
		<div class="wrap">
			<h1>🛡️ Seguridad · SolarTechonolgy</h1>

			<h2>Estado de salud</h2>
			<table class="widefat striped" style="max-width:720px">
				<tbody>
				<?php foreach ( self::health() as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r[0] ); ?></td>
						<td style="text-align:right"><?php echo $r[1] ? '<span style="color:#1b8a4b;font-weight:700">✓ OK</span>' : '<span style="color:#c0392b;font-weight:700">✕ Revisar</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:30px">Lista negra de IP (<?php echo count( $black ); ?>)</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:14px">
				<?php wp_nonce_field( 'st_ip' ); ?>
				<input type="hidden" name="action" value="st_add_ip">
				<input type="text" name="ip" placeholder="192.168.0.1" class="regular-text" required>
				<button class="button button-primary">Bloquear IP</button>
			</form>
			<table class="widefat striped" style="max-width:520px">
				<thead><tr><th>IP</th><th></th></tr></thead>
				<tbody>
				<?php if ( ! $black ) : ?>
					<tr><td colspan="2">Sin IP bloqueadas.</td></tr>
				<?php else : foreach ( $black as $ip ) : ?>
					<tr>
						<td><code><?php echo esc_html( $ip ); ?></code></td>
						<td style="text-align:right">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( 'st_ip' ); ?>
								<input type="hidden" name="action" value="st_del_ip">
								<input type="hidden" name="ip" value="<?php echo esc_attr( $ip ); ?>">
								<button class="button-link-delete button-link">Quitar</button>
							</form>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top:30px">Detección de ataques (<?php echo count( $attacks ); ?>)</h2>
			<table class="widefat striped">
				<thead><tr><th>Fecha</th><th>IP</th><th>Tipo</th><th>URI</th></tr></thead>
				<tbody>
				<?php if ( ! $attacks ) : ?>
					<tr><td colspan="4">Sin ataques registrados. 🎉</td></tr>
				<?php else : foreach ( array_slice( $attacks, 0, 60 ) as $a ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m H:i:s', $a['time'] ) ); ?></td>
						<td><code><?php echo esc_html( $a['ip'] ); ?></code></td>
						<td><strong style="color:#c0392b"><?php echo esc_html( $a['sig'] ); ?></strong></td>
						<td><small><?php echo esc_html( $a['uri'] ); ?></small></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top:30px">Visitantes recientes (<?php echo count( $visits ); ?>)</h2>
			<table class="widefat striped">
				<thead><tr><th>Fecha</th><th>IP</th><th>URI</th><th>Navegador</th></tr></thead>
				<tbody>
				<?php if ( ! $visits ) : ?>
					<tr><td colspan="4">Sin visitas registradas todavía.</td></tr>
				<?php else : foreach ( array_slice( $visits, 0, 60 ) as $v ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m H:i:s', $v['time'] ) ); ?></td>
						<td><code><?php echo esc_html( $v['ip'] ); ?></code></td>
						<td><small><?php echo esc_html( $v['uri'] ); ?></small></td>
						<td><small><?php echo esc_html( $v['ua'] ); ?></small></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

ST_Monitor::init();
