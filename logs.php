<?php
	session_start();
	include("./settings/connect_datebase.php");
	
	if (isset($_SESSION['user'])) {
		if($_SESSION['user'] != -1) {
			$user_query = $mysqli->query("SELECT * FROM `users` WHERE `id` = ".$_SESSION['user']);
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
	
	// Данные для графика активности по дням (последние 7 дней)
	$days_sql = "SELECT 
		DATE(Date) as day,
		COUNT(*) as count
	FROM `logs` 
	WHERE DATE(Date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
	
	if ($filter_type != 'all') {
		$days_sql .= " AND `Event` LIKE '%" . $mysqli->real_escape_string($filter_type) . "%'";
	}
	
	$days_sql .= " GROUP BY DATE(Date) ORDER BY day";
	$DaysQuery = $mysqli->query($days_sql);
	
	$day_labels = [];
	$day_data = [];
	
	while ($day_row = $DaysQuery->fetch_assoc()) {
		$day_labels[] = $day_row['day'];
		$day_data[] = $day_row['count'];
	}
	
	// Данные для графика активности по часам
	$hours_sql = "SELECT 
		HOUR(Date) as hour,
		COUNT(*) as count
	FROM `logs`";
	
	if ($filter_type != 'all') {
		$hours_sql .= " WHERE `Event` LIKE '%" . $mysqli->real_escape_string($filter_type) . "%'";
	}
	
	$hours_sql .= " GROUP BY HOUR(Date) ORDER BY hour";
	$HoursQuery = $mysqli->query($hours_sql);
	
	$hour_labels = [];
	$hour_data = [];
	
	// Заполняем все часы от 0 до 23
	for ($i = 0; $i < 24; $i++) {
		$hour_labels[] = sprintf("%02d:00", $i);
		$hour_data[$i] = 0;
	}
	
	while ($hour_row = $HoursQuery->fetch_assoc()) {
		$hour_data[$hour_row['hour']] = $hour_row['count'];
	}
	
	// Данные для круговой диаграммы (типы событий)
	$types_sql = "SELECT 
		SUM(CASE WHEN Event LIKE '%авторизовался%' THEN 1 ELSE 0 END) as logins,
		SUM(CASE WHEN Event LIKE '%зарегистрировался%' THEN 1 ELSE 0 END) as registrations,
		SUM(CASE WHEN Event LIKE '%комментарий%' THEN 1 ELSE 0 END) as comments,
		SUM(CASE WHEN Event LIKE '%покинул%' THEN 1 ELSE 0 END) as logouts,
		SUM(CASE WHEN Event LIKE '%сброшен%' THEN 1 ELSE 0 END) as recoveries
	FROM `logs` WHERE 1=1";
	
	if ($filter_type != 'all') {
		$types_sql .= " AND `Event` LIKE '%" . $mysqli->real_escape_string($filter_type) . "%'";
	}
	
	$TypesQuery = $mysqli->query($types_sql);
	$TypesData = $TypesQuery->fetch_assoc();
?>
<!DOCTYPE HTML>
<html>
	<head> 
		<script src="https://code.jquery.com/jquery-1.8.3.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
			.charts-container {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
				gap: 20px;
				margin: 30px 0;
			}
			.chart-card {
				background: white;
				padding: 20px;
				border-radius: 5px;
				box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			}
			.chart-title {
				font-size: 16px;
				font-weight: bold;
				margin-bottom: 15px;
				text-align: center;
				color: #333;
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
				<div class="name">Журнал событий с графиками активности</div>
				
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

				<!-- Графики активности -->
				<div class="charts-container">
					<div class="chart-card">
						<div class="chart-title">Активность по дням (последние 7 дней)</div>
						<canvas id="activityByDayChart"></canvas>
					</div>
					
					<div class="chart-card">
						<div class="chart-title">Активность по часам суток</div>
						<canvas id="activityByHourChart"></canvas>
					</div>
					
					<div class="chart-card">
						<div class="chart-title">Распределение типов событий</div>
						<canvas id="eventsByTypeChart"></canvas>
					</div>
				</div>

				<!-- Таблица событий -->
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
			// Данные для графиков
			const dayLabels = <?php echo json_encode($day_labels); ?>;
			const dayData = <?php echo json_encode($day_data); ?>;
			
			const hourLabels = <?php echo json_encode($hour_labels); ?>;
			const hourData = <?php echo json_encode(array_values($hour_data)); ?>;
			
			const typesData = {
				logins: <?php echo $TypesData['logins'] ?? 0; ?>,
				registrations: <?php echo $TypesData['registrations'] ?? 0; ?>,
				comments: <?php echo $TypesData['comments'] ?? 0; ?>,
				logouts: <?php echo $TypesData['logouts'] ?? 0; ?>,
				recoveries: <?php echo $TypesData['recoveries'] ?? 0; ?>
			};
			
			function initCharts() {
				// 1. График активности по дням
				const ctx1 = document.getElementById('activityByDayChart').getContext('2d');
				new Chart(ctx1, {
					type: 'line',
					data: {
						labels: dayLabels,
						datasets: [{
							label: 'Количество событий',
							data: dayData,
							borderColor: '#FF6384',
							backgroundColor: 'rgba(255, 99, 132, 0.1)',
							fill: true,
							tension: 0.4
						}]
					},
					options: {
						responsive: true,
						scales: {
							y: {
								beginAtZero: true,
								title: {
									display: true,
									text: 'Событий в день'
								}
							}
						}
					}
				});
				
				// 2. График активности по часам
				const ctx2 = document.getElementById('activityByHourChart').getContext('2d');
				new Chart(ctx2, {
					type: 'bar',
					data: {
						labels: hourLabels,
						datasets: [{
							label: 'Количество событий',
							data: hourData,
							backgroundColor: '#36A2EB',
							borderColor: '#1E88E5',
							borderWidth: 1
						}]
					},
					options: {
						responsive: true,
						scales: {
							y: {
								beginAtZero: true,
								title: {
									display: true,
									text: 'Событий'
								}
							},
							x: {
								title: {
									display: true,
									text: 'Час дня'
								}
							}
						}
					}
				});
				
				// 3. Круговая диаграмма типов событий
				const ctx3 = document.getElementById('eventsByTypeChart').getContext('2d');
				new Chart(ctx3, {
					type: 'pie',
					data: {
						labels: ['Авторизации', 'Регистрации', 'Комментарии', 'Выходы', 'Восст. пароля'],
						datasets: [{
							data: [
								typesData.logins,
								typesData.registrations,
								typesData.comments,
								typesData.logouts,
								typesData.recoveries
							],
							backgroundColor: [
								'#FF6384', '#36A2EB', '#4BC0C0', '#9966FF', '#FFCE56'
							]
						}]
					},
					options: {
						responsive: true,
						plugins: {
							legend: {
								position: 'bottom'
							}
						}
					}
				});
			}
			
			function resetFilters() {
				window.location.href = 'logs.php';
			}
			
			// Инициализируем графики при загрузке страницы
			$(document).ready(function() {
				initCharts();
			});
		</script>
	</body>
</html>