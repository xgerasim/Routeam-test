<?php 
// подключаемся к IMAP

$host = '{imap.yandex.ru:993/imap/ssl}INBOX';
$user = 'edumarkoff@yandex.ru';
$pass = 'zxczktsdmstsvimu';
// $username = 'test_out@routeamgroup.com';
// $password = 'zxczktsdmstsvimu';

$inbox = imap_open($host, $user, $pass, OP_READONLY);
$mails = imap_search($inbox, 'UNSEEN SUBJECT "Заявка"', 0, 'UTF-8');

// создаю подключение к БД
$connection = mysqli_connect('localhost', 'root', '', 'mails');

if (!$connection) {
	die('Ошибка подключения: ' . mysqli_connect_error());
} else {
	echo 'Всё гуд, БД подключена<br>';
};

if (!$mails) {
	echo 'Проверь данные для входа, чувак, что-то неправильно. Или просто новых писем нет';
} else {

$i = 1; foreach ($mails as $mail) {

	    $header = imap_headerinfo($inbox, $mail);

	    $subj = iconv_mime_decode($header->subject,0,"UTF-8");
	    $body = strip_tags(imap_fetchbody($inbox, $mail, 1));
         
        // загоняем данные в первую таблицу
		$created = $header->date;
		mysqli_query($connection, "INSERT INTO `mail` (`id`, `title`, `body`, `created_at`, `updated_at`) VALUES ('$i', '$subj', '$body', '$created', '$created')");
		

		// 1 - новая
		// 2 - в работе
		// 3 - готово
		// 4 - отклонено

		$status = preg_match('/"(.*?)"/', $subj, $matches);
		$status_name = $matches[1];
        $status_num = 0;
		switch ($status_name) {
		    case "Новая":
		        $status_num = 1;
		        break;
		    case "В работе":
		        $status_num = 2;
		        break;
		    case "Готово":
		        $status_num = 3;
		        break;
		    case "Отклонено":
		        $status_num = 4;
		        break;    
		}

		// задаем текущее время - для апдейта
		date_default_timezone_set('Europe/Moscow');

		$datetime = date('D, d M Y H:i:s O');
		

		/* Пока что логика такая
           Проверять, есть ли среди body в первой таблице такой id во второй

           Если нет - вписать и добавить ей номер, 
           Если да - переписать статус, вставить дату обновления (текущую дату/время) */ 

		$check_id = mysqli_query($connection, "SELECT * FROM `tickets` WHERE `id` = '$body'");
		$check_obj = mysqli_fetch_assoc($check_id);
        echo '<pre>';
		print_r($check_obj);
        echo '</pre>';

        if ($check_id->num_rows > 0) {
           mysqli_query($connection, "UPDATE `tickets` SET `status` = '$status_num', updated_at = '$datetime' WHERE `id` = '$body'");
           mysqli_query($connection, "UPDATE `mail` SET `updated_at` = `$datetime` WHERE `id` = '$i'");	
           echo 'я нашел совпадения';
        } else {
		// ща будем загонять данные во вторую таблицу

		mysqli_query($connection, "INSERT INTO `tickets` (`id`, `title`, `status`, `created_at`, `updated_at`) VALUES ($body, 'Заявка $body', '$status_num', '$created', '$created')");
		echo 'Это новая заявка';
        }

        $i++;
}

};
	
// Закрытие соединения с почтовым сервером
imap_close($inbox);
// Закрытие соединения с БД
mysqli_close($connection);