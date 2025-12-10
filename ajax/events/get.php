<?
    require_once("../../settings/connect_datebase.php");

    $Sql = "SELECT * FROM `logs` ORDER BY `Date`";
    $Query = $mysqli->query($Sql);

    $Events = array();

    while($Read = $Query->fetch_assoc()) {
        $Status = "";

        $SqlSession = "SELECT * FROM `session` WHERE `IdUser` = {$Read["IdUser"]} ORDER BY `DateStart` DESC";
        $QuerySession = $mysqli->query($SqlSession);
        if ($QuerySession->num_rows > 0) {
		    $ReadSession = $QuerySession->fetch_assoc();

		    $TimeEnd = strtotime($ReadSession["DateNow"]) + 5*60;
		    $TimeNow = time();

            if($TimeEnd > $TimeNow)
                $Status = "online";
            else {
                $TimeEnd = strtotime($ReadSession["DateNow"]);
                $TimeDelta = round(($TimeNow - $TimeEnd)/60);

                $Status = "Был в сети: {$TimeDelta} минут назад";
            }
	    }   

        $Event = array(
            "Id" => $Read["Id"],
            "Ip" => $Read["Ip"],
            "Date" => $Read["Date"],
            "TimeOnline" => $Read["TimeOnline"],
            "Status" => $Status,
            "Event" => $Read["Event"]
        );
        array_push($Events, $Event);
    }

    echo json_encode($Events, JSON_UNESCAPED_UNICODE);

?>