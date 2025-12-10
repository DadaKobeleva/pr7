<?php
	session_start();
	include("../settings/connect_datebase.php");
	
	$login = $_POST['login'];
	
	// ищем пользователя
	$query_user = $mysqli->query("SELECT * FROM `users` WHERE `login`='".$login."';");
	
	$id = -1;
	if($user_read = $query_user->fetch_row()) {
		// создаём новый пароль
		$id = $user_read[0];
	}
	
	function PasswordGeneration() {
		// создаём пароль
		$chars="qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP"; // матрица
		$max=10; // количество
		$size=StrLen($chars)-1; // Определяем количество символов в $chars
		$password="";
		
		while($max--) {
			$password.=$chars[rand(0,$size)];
		}
		
		return $password;
	}
	
	if($id != -1) {
		//обновляем пароль
		$password = PasswordGeneration();
		// проверяем не используется ли пароль 
		$query_password = $mysqli->query("SELECT * FROM `users` WHERE `password`= '".md5($password)."';");
		while($password_read = $query_password->fetch_row()) {
			// создаём новый пароль
			$password = PasswordGeneration();
		}
		// обновляем пароль
		$mysqli->query("UPDATE `users` SET `password`='".md5($password)."' WHERE `login` = '".$login."'");
		
		$Ip = $_SERVER["REMOTE_ADDR"];
		$Date = date(format: "Y-m-d H:i:s");
		
		if(isset($_SESSION['user'])) {
			$current_user_id = $_SESSION['user'];
		} else {
			$current_user_id = $id;
		}
		
		$Sql = "INSERT INTO `logs`(`Ip`, `IdUser`, `Date`, `TimeOnline`, `Event`) VALUES ('{$Ip}','{$current_user_id}','{$Date}','00:00:00','Пароль пользователя {$login} (ID: {$id}) был сброшен. Новый пароль: {$password}')";
		$mysqli->query(query: $Sql);
		
		// отсылаем на почту (раскомментировать когда будет настроена отправка)
		// mail($login, 'Безопасность web-приложений КГАПОУ "Авиатехникум"', "Ваш пароль был только что изменён. Новый пароль: ".$password);
		
		echo $password;
	} else {
		echo "error";
	}
?>