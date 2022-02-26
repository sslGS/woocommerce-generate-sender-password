<?php
//Sender new password on SMS && Generate Password && Check Request
function wp_generate_password_sms( $length = 5, $password = '', $chars = 'QWRUSDFGJZV12345' ) {
	for ( $i = 0; $i < $length; $i++ ) { 
		$password .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 ); 
	};
	
	return $password;
};

add_shortcode( 'sms_lost-password-form', 'render_sms_lost_password_form' );
function render_sms_lost_password_form() {
 
	if ( is_user_logged_in() ) {
		return sprintf( "Вы уже авторизованы на сайте. <a href='%s'>Выйти</a>.", wp_logout_url() );
	}

	$return = '';
 
	if ( isset( $_REQUEST['request_status'] ) ) {
		$errors = explode( ',', $_REQUEST['request_status'] );
 
		foreach ( $errors as $error ) {
			switch ( $error ) {
				case 'empty_username':
					$return .= '<div class="woocommerce-notices-wrapper"><ul class="woocommerce-error" role="alert"><li>Введите номер телефона или email.</li></ul></div>';
					break;
				case 'invalid_email':
				case 'invalidcombo':
					$return .= '<div class="woocommerce-notices-wrapper"><ul class="woocommerce-error" role="alert"><li>Учетная запись не найдена по данным.</li></ul></div>';
					break;
                case 'not_allowed': 
                    $return .= '<div class="woocommerce-notices-wrapper"><ul class="woocommerce-error" role="alert"><li>Для данной учетной записи запрещено.</li></ul></div>';
                    break;
                case 'confirm':
                    $return .= '<div class="woocommerce-notices-wrapper"><ul class="woocommerce-message" role="alert"><li>Новый пароль успешно отправлен по номеру телефона.</li></ul></div>';
                    break;
                default:
                    $return .= '<div class="woocommerce-notices-wrapper"><ul class="woocommerce-error" role="alert"><li>Что-то пошло не так.</li></ul></div>';
                    break;
			}
		}
	}

	$return .= '
		<form class="woocommerce-ResetPassword lost_reset_password" method="post">
            <p>Забыли свой пароль? Укажите свой Email или номер телефона. Новый пароль вы получите по SMS.</p>
			<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
				<label for="user_login">Номер телефона или Email</label>
				<input class="woocommerce-Input woocommerce-Input--text input-text" type="text" name="user_login" id="user_login">
			</p>
            <div class="clear"></div>
 			<p class="woocommerce-form-row form-row">
				<input class="woocommerce-Button button" type="submit" name="submit" value="Сброс пароля" />
				<input type="hidden" name="hidden_value_sms" value="true" />
			</p>
		</form>';

	return $return;
}

add_action( 'woocommerce_after_lost_password_form', function() {
    $url = get_page_by_path('lost-password-sms');
    $url = get_permalink($url);
    echo "<a href='${url}'>Отправка пароля по SMS</a>";
});

if ( isset ($_POST['hidden_value_sms'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && !is_user_logged_in() ) {
	$login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
	$forgot_pass_page_slug = 'lost-password-sms/';
	$user_password = '';
	if ( empty( $login ) ) {
		$to = site_url( $forgot_pass_page_slug );
		$to = add_query_arg( 'request_status', 'empty_username', $to );
		header("Location: {$to}");
		exit;
	} else {
		$user_data = get_user_by( 'login', $login );
	};

	if ( ! $user_data && is_email( $login ) && apply_filters( 'woocommerce_get_username_from_email', true ) ) {
        $user_data = get_user_by( 'email', $login );
    };

    if ( ! $user_data ) {
        $to = site_url( $forgot_pass_page_slug );
        $to = add_query_arg( 'request_status', 'invalidcombo', $to );
        header("Location: {$to}");
		exit;
    };

    if ( is_multisite() && ! is_user_member_of_blog( $user_data->ID, get_current_blog_id() ) ) {
        $to = site_url( $forgot_pass_page_slug );
        $to = add_query_arg( 'request_status', 'invalidcombo', $to );
        header("Location: {$to}");
		exit;
    };

	$user_login = $user_data->user_login;
    $allow = apply_filters( 'allow_password_reset', true, $user_data->ID );

    if ( ! $allow ) {
        $to = site_url( $forgot_pass_page_slug );
        $to = add_query_arg( 'request_status', 'not_allowed', $to );
        header("Location: {$to}");
		exit;
    } elseif ( is_wp_error( $allow ) ) {
        $to = site_url( $forgot_pass_page_slug );
        $to = add_query_arg( 'request_status', 'not_allowed', $to );
        header("Location: {$to}");
		exit;
    };

    require_once( get_stylesheet_directory() . '/classes/sms-service.php' );
	$user_password = wp_generate_password_sms();
	wp_set_password( $user_password, $user_data->ID);
    $sms = new SMS_Service("Новый пароль : {$user_password}. все-пестициды.рф.", $fields);
    $res = $sms->send($user_login);
	$to = site_url( $forgot_pass_page_slug );
	$to = add_query_arg( 'request_status', 'confirm', $to );
    header("Location: {$to}");
	exit;
};