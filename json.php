<?php
include(dirname(__FILE__) . '/common.php');
SetStyle('api', 'API');

switch ($_GET['action']) {
	case 'get_notifications':
		Auth(1);
		header("Cache-Control: no-cache, must-revalidate");
		set_time_limit(0);
		//如果是自己的服务器，建议调大超时时间，然后把长连接时长调大，以节约服务器资源
		$Config['PushConnectionTimeoutPeriod'] = intval((intval($Config['PushConnectionTimeoutPeriod']) < 22) ? 22 : $Config['PushConnectionTimeoutPeriod']);
		while ((time() - $TimeStamp) < $Config['PushConnectionTimeoutPeriod']) {
			if ($MCache) {
				$CurUserInfo = $MCache->get(MemCachePrefix . 'UserInfo_' . $CurUserID);
				if ($CurUserInfo) {
					$CurNewMessage = $CurUserInfo['NewMessage'];
				} else {
					$TempUserInfo = $DB->row("SELECT * FROM " . $Prefix . "users WHERE ID = :UserID", array(
						"UserID" => $CurUserID
					));
					$MCache->set(MemCachePrefix . 'UserInfo_' . $CurUserID, $TempUserInfo, 86400);
					$CurNewMessage = $TempUserInfo['NewMessage'];
				}
			} else {
				$CurNewMessage = $DB->single("SELECT NewMessage FROM " . $Prefix . "users WHERE ID = :UserID", array(
					"UserID" => $CurUserID
				));
			}
			
			if ($CurNewMessage > 0) {
				break;
			}
			sleep(3);
		}
		echo json_encode(array(
			'Status' => 1,
			'NewMessage' => $CurNewMessage
		));
		break;
	
	
	case 'get_tags':
		Auth(1);
		require(dirname(__FILE__) . "/includes/PHPAnalysis.class.php");
		$str                   = $_POST['Title'] . "/r/n" . $_POST['Content'];
		$do_fork               = $do_unit = true;
		$do_multi              = $do_prop = $pri_dict = false;
		//初始化类
		PhpAnalysis::$loadInit = false;
		$pa                    = new PhpAnalysis('utf-8', 'utf-8', $pri_dict);
		//载入词典
		$pa->LoadDict();
		//执行分词
		$pa->SetSource($str);
		$pa->differMax = $do_multi;
		$pa->unitWord  = $do_unit;
		$pa->StartAnalysis($do_fork);
		$ResultString   = $pa->GetFinallyResult('|', $do_prop);
		$tags           = array();
		$tags['status'] = 0;
		if ($ResultString) {
			foreach (explode('|', $ResultString) as $key => $value) {
				if ($value != '' && !is_numeric($value) && mb_strlen($value, "utf-8") >= 2) {
					$SQLParameters[] = $value;
				}
			}
			$TagsLists1 = $DB->column("SELECT Name FROM " . $Prefix . "tags Where Name IN (?)", $SQLParameters);
			$TagsLists2 = $DB->column("SELECT Title FROM " . $Prefix . "dict Where Title IN (?) Group By Title", $SQLParameters);
			//$TagsLists2 = array();
			$TagsLists  = array_merge($TagsLists1, array_diff($TagsLists2, $TagsLists1));
			if ($TagsLists) {
				$tags['status'] = 1;
				rsort($TagsLists);
				$tags['lists'] = $TagsLists;
			}
		}
		echo json_encode($tags);
		break;
	
	
	case 'tag_autocomplete':
		//Auth(1);
		$Keyword           = $_POST['query'];
		$Response          = array();
		$Response['query'] = 'Unit';
		$Result            = $DB->column("SELECT Title FROM " . $Prefix . "dict WHERE Title LIKE :Keyword limit 10", array(
			"Keyword" => $Keyword . "%"
		));
		if ($Result) {
			foreach ($Result as $key => $val) {
				$Response['suggestions'][] = array(
					'value' => $val,
					'data' => $val
				);
			}
		} else {
			$Response['suggestions'][] = '';
		}
		echo json_encode($Response);
		break;
	
	case 'user_exist':
		$UserName  = strtolower(Request('Post', 'UserName'));
		$UserExist = $DB->single("SELECT ID FROM " . $Prefix . "users WHERE UserName = :UserName", array(
			'UserName' => $UserName
		));
		echo json_encode(array(
			'Status' => $UserExist ? 1 : 0
		));
		break;
	
	case 'get_post':
		$PostId = intval($_POST['PostId']);
		$row    = $DB->row("SELECT UserName, Content, TopicID FROM {$Prefix}posts WHERE ID = :PostId AND IsDel = 0", array(
			'PostId' => $PostId
		));
		if ($CurUserRole < 4) {
			// 对超级管理员以下的用户需要检查整个主题是否被删除了
			$TopicID  = $row['TopicID'];
			$TopicRow = $DB->single("SELECT COUNT(*) FROM {$Prefix}topics WHERE ID = :TopicID AND IsDel = 0", array(
				'TopicID' => $TopicID
			));
			if ($TopicRow < 1) {
				$row = false;
			}
		}
		echo json_encode($row);
		break;
	
	default:
		# code...
		break;
}