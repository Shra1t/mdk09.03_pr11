<?php
	session_start();
	include("../settings/connect_datebase.php");

	function decryptAES($encryptedData, $key) {
		$data = base64_decode($encryptedData);
		
		if ($data === false || strlen($data) < 17) {
			error_log("Invalid data or too short");
			return false;
		}

		$iv = substr($data, 0, 16);
		$encrypted = substr($data, 16);

		$keyHash = md5($key);
		$keyBytes = hex2bin($keyHash);

		$decrypted = openssl_decrypt(
			$encrypted,
			'aes-128-cbc',
			$keyBytes,
			OPENSSL_RAW_DATA,
			$iv
		);

		return $decrypted;
	}

	$login_encrypted = $_POST['login'] ?? '';
	$password_encrypted = $_POST['password'] ?? '';

	$secretKey = "qazxswedcvrftgbn";

	$login = decryptAES($login_encrypted, $secretKey);
	$password = decryptAES($password_encrypted, $secretKey);
	
	// ищем пользователя
	$query_user = $mysqli->query("SELECT * FROM `users` WHERE `login`='".$login."' AND `password`= '".$password."';");
	
	$id = -1;
	while($user_read = $query_user->fetch_row()) {
		$id = $user_read[0];
	}
	
	if($id != -1) {
		$_SESSION['user'] = $id;
	}
	echo md5(md5($id));
?>