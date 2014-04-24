<?php
/**
 * @copyright Дмитрий Гегеня http://progroup.by
 * Скрипт автоматического переноса сайта на MODX Revolution
 * Работа проверялась на Debian, должны быть в системе: tar и mysqldump
 * Скрипт чепорный и нет защиты от "дурака".
 * Руководство ))) >>>>
 * Скопировать в корневую директорию вашего сайта и запустить этот файл. 
 * После создания архива, его и это файл перенести в новую корневую директорию, 
 * ввести новые параметры базы данных и нажать кнопку распаковать архив, 
 * база данных должна быть пустой.
 * После распаковки все "рабочие" (файл конфигурации, дампа базы данных а при распаковке и сам архив) файлы удаляются. 
 */
error_reporting(E_ALL);
set_time_limit(0); 

$dbbkname = 'dbbk-'.date('ymd').'.sql'; //имя дампа базы данных
$stbkname = 'stbk-'.date('ymd').'.tar.gz';  //имя файла бекапа
$configFile = 'mycfg.txt';
$paramArray = array();

?>
<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8" />
        <title>Перенос MODX Revo</title>
    </head>
    <body>

<?php

if ($_GET['action']=="backup") {
	include(dirname(__FILE__) . '/config.core.php');
	include (MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php');
	$file = fopen($configFile,'w');
	$paramArray = array(
		'database_server'=>$database_server,
		'database_user'=>$database_user,
		'database_password'=>$database_password,
		'dbase'=>$dbase,
		'modx_base_path'=>$modx_base_path,
		'modx_connectors_url'=>$modx_connectors_url,
		'modx_manager_url'=>$modx_manager_url,
	);
	fwrite($file, serialize($paramArray));
   	fclose($file);
	
	
	if (!file_exists($dbbkname)&&(!file_exists($stbkname))) {
		shell_exec('mysqldump --user='.$database_user.' --password='.$database_password.' --host='.$database_server.' '.$dbase.' > '.$dbbkname);
	}
	if (!file_exists($stbkname)) {
		shell_exec('tar -czf'.$stbkname.' *  --exclude='.$stbkname.' --exclude=core/cache --exclude=relocation.php');
		shell_exec('rm '.$dbbkname); //удаляем дамп базы
		shell_exec('rm '.$configFile); //удаляем файл конфигурации
	}	
?>
	<a href="<?php echo($stbkname) ?>">Скачать архив <?php echo($stbkname) ?></a>
<?php

}
elseif ($_GET['action']=="unpack") {
	$currenBasePatch = dirname(__FILE__).'/';
	if (file_exists($stbkname)) {
		shell_exec('tar -xf'.$stbkname);
		$file = file_get_contents($configFile);
		$paramArray = unserialize($file);
		
		$fileName[] = 'config.core.php'; //корневой файл
		$fileName[] = substr($paramArray['modx_connectors_url'],1).'config.core.php';
		$fileName[] = substr($paramArray['modx_manager_url'],1).'config.core.php';

		foreach($fileName as $currenConfigFile) {
			$file = file_get_contents($currenConfigFile);
			$file = str_replace($paramArray['modx_base_path'], $currenBasePatch, $file);
			file_put_contents($currenConfigFile, $file);
		}

		//заменяем параметры в файле конфигурации на новые
		include(dirname(__FILE__) . '/config.core.php');
		$file = file_get_contents(MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php');
		//заменяем пути
		$file = str_replace($paramArray['modx_base_path'], $currenBasePatch, $file);
		//заменяем параметры базы данных
		$file = str_replace('$database_server = \''.$paramArray['database_server'].'\';', '$database_server = \''.$_GET['newDbHost'].'\';', $file);
		$file = str_replace('$database_user = \''.$paramArray['database_user'].'\';', '$database_user = \''.$_GET['newDbUser'].'\';', $file);
		$file = str_replace('$database_password = \''.$paramArray['database_password'].'\';', '$database_password = \''.$_GET['newDbPassw'].'\';', $file);
		$file = str_replace('$dbase = \''.$paramArray['dbase'].'\';', '$dbase = \''.$_GET['newDbName'].'\';', $file);
		$file = str_replace('dbname='.$paramArray['dbase'].';', 'dbname='.$_GET['newDbName'].';', $file);
		file_put_contents(MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php', $file);
		
		shell_exec('mysql --user='.$_GET['newDbUser'].' --password='.$_GET['newDbPassw'].' --host='.$_GET['newDbHost'].' '.$_GET['newDbName'].' < '.$dbbkname);
		shell_exec('rm '.$dbbkname); //удаляем дамп базы
		shell_exec('rm '.$configFile); //удаляем файл конфигурации
		shell_exec('rm '.$stbkname); //удаляем архив

?>
		<p>Архив распакован и конфиги перезаписаны. Перейти на <a href="index.php">сайт</a> или в <a href="<?php echo($paramArray['modx_manager_url']) ?>">админку</a></p>
<?php

	}
	else {
?>
	<p>Архив не найден</p>
<?php
	}
}
else {
?>
	<a href="?action=backup">Создать архив</a><br /><br />
	<form method="get" action="relocation.php">
		<input type="hidden" name="action" value="unpack">
		База данных: <input type="text" name="newDbName" value=""><br />
		Хост: <input type="text" name="newDbHost" value="localhost"><br />
		Пользователь: <input type="text" name="newDbUser" value=""><br />
		Пароль: <input type="text" name="newDbPassw" value=""><br />
		<input type="submit" value="Распаковать архив">
	</form>	
<?php
}
?>
	</body>
</html>