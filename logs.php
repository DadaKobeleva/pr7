<?php
	session_start();
	include("./settings/connect_datebase.php");
	
	if (isset($_SESSION['user'])) {
		if($_SESSION['user'] != -1) {
			$user_query = $mysqli->query("SELECT * FROM `users` WHERE `id` = ".$_SESSION['user']); // проверяем
			while($user_read = $user_query->fetch_row()) {
				if($user_read[3] == 0) header("Location: index.php");
			}
		} else header("Location: login.php");
 	} else {
		header("Location: login.php");
		echo "Пользователя не существует";
	}

	include("./settings/session.php");
?>
<!DOCTYPE HTML>
<html>
	<head> 
		<script src="https://code.jquery.com/jquery-1.8.3.js"></script>
		<meta charset="utf-8">
		<title> Admin панель </title>
		
		<link rel="stylesheet" href="style.css">

		<style>
			table {
				width: 100%;
			}
			td {
				text-align: center;
				padding: 10px;
			}
		</style>
	</head>
	<body>
		<div class="top-menu">

			<a href=#><img src = "img/logo1.png"/></a>
			<div class="name">
				<a href="index.php">
					<div class="subname">БЗОПАСНОСТЬ  ВЕБ-ПРИЛОЖЕНИЙ</div>
					Пермский авиационный техникум им. А. Д. Швецова
				</a>
			</div>
		</div>
		<div class="space"> </div>
		<div class="main">
			<div class="content">
				<div class="name">Журнал событий</div>

				<table border="1">
					<tr>
						<td style="width: 165px;">Дата и время</td>
						<td style="width: 165px;">IP пользователя</td>
						<td style="width: 165px;">Время в сети</td>
						<td style="width: 165px;">Статус</td>
						<td>Произошедшее событие</td>
					</tr>
				</table>
			
				<div class="footer">
					© КГАПОУ "Авиатехникум", 2020
					<a href=#>Конфиденциальность</a>
					<a href=#>Условия</a>
				</div>
			</div>
		</div>
		
		<script>
			GetEvents();
			function GetEvents() {
				// AJAX запрос
				$.ajax({
					url         : 'ajax/events/get.php',
					type        : 'POST', // важно!
					data        : null,
					cache       : false,
					dataType    : 'html',
					processData : false,
					contentType : false, 
					// функция успешного ответа сервера
					success: GetEventsAjax,
					// функция ошибки
					error: function( ){
						console.log('Системная ошибка!');
					}
				});
			}

			function GetEventsAjax(_data) {
				console.log(_data);

				let $Table = $("table > tbody");
				let Events = JSON.parse(_data);

				Events.forEach((Event) => {
					$Table.append(`
						<tr>
							<td>${Event["Date"]}</td>
							<td>${Event["Ip"]}</td>
							<td>${Event["TimeOnline"]}</td>
							<td>${Event["Status"]}</td>
							<td style="text-align: left">${Event["Event"]}</td>
						</tr>
					`);
				})
			}
		</script>
	</body>
</html>