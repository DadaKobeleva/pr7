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
	
	// Получаем параметры фильтрации
	$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
	$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
	$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
	$filter_search = isset($_GET['search']) ? $_GET['search'] : '';
	
	// Формируем SQL с фильтрами
	$sql = "SELECT * FROM `logs` WHERE 1=1";
	
	if ($filter_type != 'all') {
		$sql .= " AND `Event` LIKE '%" . $mysqli->real_escape_string($filter_type) . "%'";
	}
	
	if (!empty($filter_date_from)) {
		$sql .= " AND DATE(`Date`) >= '" . $mysqli->real_escape_string($filter_date_from) . "'";
	}
	
	if (!empty($filter_date_to)) {
		$sql .= " AND DATE(`Date`) <= '" . $mysqli->real_escape_string($filter_date_to) . "'";
	}
	
	if (!empty($filter_search)) {
		$sql .= " AND (`Event` LIKE '%" . $mysqli->real_escape_string($filter_search) . "%' 
		              OR `Ip` LIKE '%" . $mysqli->real_escape_string($filter_search) . "%')";
	}
	
	$sql .= " ORDER BY `Date`";
	$Query = $mysqli->query($sql);
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
			.filters {
				margin-bottom: 20px;
				padding: 15px;
				background: #f5f5f5;
				border-radius: 5px;
			}
			.filter-row {
				display: flex;
				gap: 10px;
				margin-bottom: 10px;
				flex-wrap: wrap;
			}
			.filter-group {
				display: flex;
				flex-direction: column;
			}
			.filter-label {
				font-size: 12px;
				color: #666;
				margin-bottom: 3px;
			}
			.filter-select, .filter-input {
				padding: 5px;
				border: 1px solid #ccc;
				border-radius: 3px;
			}
			.filter-button {
				padding: 5px 15px;
				background: #0066cc;
				color: white;
				border: none;
				border-radius: 3px;
				cursor: pointer;
				height: 29px;
				align-self: flex-end;
			}
			.filter-button:hover {
				background: #0055aa;
			}
			.status-online {
				color: green;
				font-weight: bold;
			}
			.no-data {
				text-align: center;
				padding: 20px;
				color: #666;
				font-style: italic;
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
				
				<!-- Форма фильтрации -->
				<div class="filters">
					<form method="GET" action="">
						<div class="filter-row">
							<div class="filter-group">
								<div class="filter-label">Тип события</div>
								<select class="filter-select" name="type">
									<option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>Все события</option>
									<option value="авторизовался" <?php echo $filter_type == 'авторизовался' ? 'selected' : ''; ?>>Авторизация</option>
									<option value="зарегистрировался" <?php echo $filter_type == 'зарегистрировался' ? 'selected' : ''; ?>>Регистрация</option>
									<option value="сброшен" <?php echo $filter_type == 'сброшен' ? 'selected' : ''; ?>>Восстановление пароля</option>
									<option value="комментарий" <?php echo $filter_type == 'комментарий' ? 'selected' : ''; ?>>Комментарии</option>
									<option value="покинул" <?php echo $filter_type == 'покинул' ? 'selected' : ''; ?>>Выход</option>
								</select>
							</div>
							
							<div class="filter-group">
								<div class="filter-label">Дата с</div>
								<input type="date" class="filter-input" name="date_from" value="<?php echo $filter_date_from; ?>">
							</div>
							
							<div class="filter-group">
								<div class="filter-label">Дата по</div>
								<input type="date" class="filter-input" name="date_to" value="<?php echo $filter_date_to; ?>">
							</div>
							
							<div class="filter-group">
								<div class="filter-label">Поиск</div>
								<input type="text" class="filter-input" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Текст или IP">
							</div>
							
							<button type="submit" class="filter-button">Применить</button>
							<button type="button" class="filter-button" onclick="resetFilters()">Сбросить</button>
						</div>
					</form>
				</div>

				<table border="1">
					<tr>
						<td style="width: 165px;">Дата и время</td>
						<td style="width: 165px;">IP пользователя</td>
						<td style="width: 165px;">Время в сети</td>
						<td style="width: 165px;">Статус</td>
						<td>Произошедшее событие</td>
					</tr>
					
					<?php
					if ($Query->num_rows > 0) {
						while($Read = $Query->fetch_assoc()) {
							$Status = "";
							$SqlSession = "SELECT * FROM `session` WHERE `IdUser` = {$Read["IdUser"]} ORDER BY `DateStart` DESC";
							$QuerySession = $mysqli->query($SqlSession);
							
							if ($QuerySession->num_rows > 0) {
								$ReadSession = $QuerySession->fetch_assoc();
								$TimeEnd = strtotime($ReadSession["DateNow"]) + 5*60;
								$TimeNow = time();

								if($TimeEnd > $TimeNow) {
									$Status = "online";
									$status_class = "status-online";
								} else {
									$TimeEnd = strtotime($ReadSession["DateNow"]);
									$TimeDelta = round(($TimeNow - $TimeEnd)/60);
									$Status = "Был в сети: {$TimeDelta} минут назад";
									$status_class = "";
								}
							} else {
								$Status = "Никогда не был онлайн";
								$status_class = "";
							}
							
							echo "<tr>";
							echo "<td>{$Read["Date"]}</td>";
							echo "<td>{$Read["Ip"]}</td>";
							echo "<td>{$Read["TimeOnline"]}</td>";
							echo "<td class='{$status_class}'>{$Status}</td>";
							echo "<td style='text-align: left'>{$Read["Event"]}</td>";
							echo "</tr>";
						}
					} else {
						echo "<tr><td colspan='5' class='no-data'>События не найдены</td></tr>";
					}
					?>
				</table>
			
				<div class="footer">
					© КГАПОУ "Авиатехникум", 2020
					<a href=#>Конфиденциальность</a>
					<a href=#>Условия</a>
				</div>
			</div>
		</div>
		
		<script>
			function resetFilters() {
				window.location.href = 'logs.php';
			}
			
			// Автообновление каждые 60 секунд (опционально)
			// setTimeout(function() {
			//     window.location.reload();
			// }, 60000);
		</script>
	</body>
</html>