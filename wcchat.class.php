<?php

/**
 * WaterCooler Chat (Main Class file)
 * 
 * @version 1.4
 * @author Jo�o Ferreira <jflei@sapo.pt>
 * @copyright (c) 2018, Jo�o Ferreira
 */

// DO NOT MAKE CHANGES TO THIS FILE

class WcChat {

  private $name;
	private $includeDir;
	private $ajaxCaller;
	private $avSource;
	private $avResize_w;
	private $avResize_h;
	private $userLink;
	private $userEmail;
	private $userTimezone;
	private $hFormat;
	private $joinMsg;
	private $userDataString;
	private $udata;
	private $templates;
	private $modList;
	private $mutedList;
	private $msgList;
	private $hiddenMsgList;
	private $userList;
	private $bannedList;
	private $eventList;
	private $topic;
	private $isMod = FALSE;
	private $isMasterMod = FALSE;
	private $isBanned = FALSE;
	private $embedded;
	private $isCertifiedUser = FALSE;
	private $hasProfileAccess = FALSE;
	private $stopMsg;
	private $dataDir;
	private $roomDir;

	public function __construct(
			$embedded = NULL) {

		$this->embedded = $embedded;

		if(preg_match("/bot|crawl|spider|slurp|archiver|mediapartners|agent|scraper|cloudflare|facebookexternalhit/i", $this->myServer('HTTP_USER_AGENT'))) { die(); }

		session_start();

		if($this->myGet('cuser')) {
			unset($_SESSION['cname']);
			unset($_SESSION['login_err']);
			setcookie('cname', '', time()-3600, '/');
			setcookie('chatpass', '', time()-3600, '/');
			header('location: '.$this->myGet('ret').'#wc_topic');
			die();
		}

		include(__DIR__ . '/settings.php');
		$this->includeDir = (INCLUDE_DIR ? rtrim(INCLUDE_DIR, '/') . '/' : '');
		define('THEME', (($this->myCookie('wc_theme') && file_exists($this->includeDir . 'themes/' . $this->myCookie('wc_theme') . '/')) ? $this->myCookie('wc_theme') : DEFAULT_THEME));
		define('INCLUDE_DIR_THEME', $this->includeDir . 'themes/' . THEME . '/');
		include(__DIR__ . '/themes/' . THEME . '/templates.php');
		$this->templates = $templates;
		$this->ajaxCaller = $this->includeDir.'ajax.php?';
		$this->dataDir = (DATA_DIR ? rtrim(DATA_DIR, '/') . '/' : '');
		$this->roomDir = DATA_DIR . 'rooms/';

		if(!$this->mySession('current_room')) {
			$_SESSION['current_room'] = (($this->myCookie('current_room') && file_exists($this->roomDir . base64_encode($this->myCookie('current_room')).'.txt')) ? $this->myCookie('current_room') : DEFAULT_ROOM);
		} elseif(!file_exists($this->roomDir . base64_encode($this->mySession('current_room')).'.txt')) {
			$_SESSION['current_room'] = DEFAULT_ROOM;
			$_SESSION['reset_msg'] = '1';
			setcookie('current_room', DEFAULT_ROOM, time()+(86400*365), '/');
		}

		$hasNonWritableFolders = $this->hasNonWritableFolders();
		if($hasNonWritableFolders) {
			echo $this->pTemplate(
				'index.critical_error', 
				array(
					'TITLE' => TITLE,
					'ERROR' => 'Non Writable Data / File directories exist, please set write permissions to the following directories:<br><br>'.$hasNonWritableFolders
				)
			);
			die(); 
		} else {
			$this->initDataFiles();
		}

		if($this->mySession('current_room') == DEFAULT_ROOM && strlen($this->msgList) == 0) {
			$towrite = time().'|*'.base64_encode('room').'|has been created.'."\n";
			$this->writeFile(MESSAGES_LOC, $towrite, 'w');
			$this->msgList = file_get_contents(MESSAGES_LOC);
		}

		$u = $this->myGet('u');
		if($this->myGet('recover') && $u && file_exists($this->dataDir . 'tmp/rec_'.$u)) {
			$par = $this->userData(base64_decode($u));
			$par2 = $this->userMatch(base64_decode($u), NULL, 'return_match');
			if($par[5] == $this->myGet('recover')) {
				$npass = $this->randNumb(8);
				$npasse = md5(md5($npass));
				$ndata = base64_encode(base64_encode($par[0]).'|'.base64_encode($par[1]).'|'.base64_encode($par[2]).'|'.$par[3].'|'.$par[4].'|'.$npasse);
				$request_uri = explode('?', $_SERVER['REQUEST_URI']);
				if(mail(
					$par[1],
					'Account Recovery',
					"Your new password is: ".$npass."\n\n\n".((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://').$_SERVER['SERVER_NAME'].$request_uri[0],
					$this->mailHeaders(
						(trim(ACC_REC_EMAIL) ? trim(ACC_REC_EMAIL) : 'no-reply@'.$_SERVER['SERVER_NAME']),
						TITLE,
						$par[1],
						base64_decode($u)
					)
				)) {
					$this->writeFile(USERL, str_replace($u.'|'.$par2[1].'|', $u.'|'.$ndata.'|', $this->userList), 'w');
					$this->stopMsg = 'A message was sent to the account email with the new password.';
					unlink($this->dataDir . 'tmp/rec_'.$u);
				} else {
					$this->stopMsg = 'Failed to send E-mail!';
				};
			}
		} elseif($this->myGet('recover') && $u && !file_exists($this->dataDir . 'tmp/rec_'.$u)) {
			$this->stopMsg = 'This recovery link has expired.';
		}

		if($this->myPost('cname')) {
			if(!$this->hasPermission('LOGIN', 'skip_msg')) {
				$_SESSION['login_err'] = 'Cannot login! Access Denied!';
				header('location: '.$this->myServer('REQUEST_URI').'#wc_join'); die();
			}
			if(INVITE_LINK_CODE && $this->myGet('invite') != INVITE_LINK_CODE && $this->userMatch($_POST['cname']) === FALSE) {
				$_SESSION['login_err'] = 'Cannot login! You must follow an invite link to login the first time!';
				header('location: '.$this->myServer('REQUEST_URI').'#wc_join'); die();
			}
			if(strlen(trim($this->myPost('cname'), ' ')) == 0 || strlen(trim($this->myPost('cname'), ' ')) > 30 || preg_match("/[\?<>\$\{\}\"\:\|,; ]/i", $this->myPost('cname'))) {
				$_SESSION['login_err'] = 'Invalid Nickname, too long (max = 30) or<br>with invalid characters (<b>? < > $ { } " : | , ; space</b>)!';
				header('location: '.$this->myServer('REQUEST_URI').'#wc_join'); die();
			}
			if(preg_match('/guest/i', $this->myPost('cname')))
			{
				$_SESSION['login_err'] = 'Username "Guest" is reserved AND cannot be used!';
				header('location: '.$this->myServer('REQUEST_URI').'#wc_join'); die();
			}
			$last_seen = $this->getPing($this->myPost('cname'));

			if(!$this->mySession('cname') && ((time()-$last_seen) < OFFLINE_PING)) {
				$tmp = $this->userData($this->myPost('cname'));
				if($this->myCookie('chatpass') == $tmp[4] && $this->hasData($tmp[4]) && $this->hasData($this->myCookie('chatpass'))) { $passok = TRUE; } else { $passok = FALSE; }
				if($passok === FALSE) {
					$_SESSION['login_err'] = 'Nickname <b>'.$this->myPost('cname').'</b> is currenly in use!';
					header('location: '.$this->myServer('REQUEST_URI').'#wc_join'); die();
				}
			}
			setcookie('cname', trim($this->myPost('cname'), ' '), time()+(86400*365), '/');
			$_SESSION['cname'] = trim($this->myPost('cname'), ' ');
			header('location: '.$this->myServer('REQUEST_URI').'#wc_join'); die();
		}
		$this->name = ($this->myPost('cname') ? trim($this->myPost('cname'), ' ') : $this->myCookie('cname'));

		if(!$this->name && $this->mySession('cname')) {
			$this->name = $this->mySession('cname');
		}
		if(!$this->name && !$this->mySession('cname')) {
			$this->name = 'Guest';
		}

		if($this->name && $this->name != 'Guest') {
			$this->isBanned = $this->getBanned($this->name);
		}

		$this->uData = $this->userData();
	
		if($this->hasData($this->uData[5]) && $this->myCookie('chatpass') && $this->myCookie('chatpass') == $this->uData[5]) {
			$this->isCertifiedUser = TRUE;
		}

		if($this->mySession('cname') && ($this->isCertifiedUser || !$this->uData[5])) {
			$this->hasProfileAccess = TRUE;
		}

		if(strlen(trim($this->modList)) == 0 && $this->isCertifiedUser) {
			$this->writeFile(MODL, "\n".base64_encode($this->name), 'w');
			touch(ROOMS_LASTMOD);
			$_SESSION['alert_msg'] = 'You have been assigned Master Moderator, reload page in order to get all moderator tools.';
		}

		if($this->name && $this->isCertifiedUser) {
			if(strpos($this->modList, base64_encode($this->name)) !== FALSE) { $this->isMod = TRUE; }
			$mods = explode("\n", trim($this->modList));
			if($mods[0] == base64_encode($this->name)) {
				$this->isMasterMod = TRUE;
			}
		}

		$this->avSource = ($this->uData[0] != '' ? $this->uData[0] : '');
		$this->userEmail = ($this->uData[1] != '' ? $this->uData[1] : '');
		$this->userLink = ($this->uData[2] != '' ? $this->uData[2] : '');
		$this->userTimezone = ($this->uData[3] != '' ? $this->uData[3] : 0);
		$this->hFormat = ($this->uData[4] != '' ? $this->uData[4]: 0);

		if(AVATAR_SIZE) {
			$this->avResize_w = $this->avResize_h = AVATAR_SIZE;
		} else {
			$this->avResize_w = $this->avResize_h = 25;
		}

		$this->userDataString = base64_encode(
			base64_encode($this->avSource).'|'.
			base64_encode($this->userEmail).'|'.
			base64_encode($this->userLink).'|'.
			$this->userTimezone.'|'.
			$this->hFormat.'|'.
			$this->uData[5]
		);

		if($this->myGet('mode')) {
			$this->ajax($this->myGet('mode'));
		} else {
			echo $this->printIndex();
		}
	}

	private function hasNonWritableFolders() {

		$output = '';
		$tmp1 = is_writable($this->roomDir);
		$tmp2 = is_writable($this->dataDir);
		$tmp3 = is_writable($this->dataDir . 'tmp/');
		$tmp4 = is_writable(__DIR__ . '/files/');
		$tmp5 = is_writable(__DIR__ . '/files/attachments/');
		$tmp6 = is_writable(__DIR__ . '/files/avatars/');
		$tmp7 = is_writable(__DIR__ . '/files/thumb/');
		if(!$tmp1 || !$tmp2 || !$tmp3 || !$tmp4 || !$tmp5 || !$tmp6 || !$tmp7) {
			if(!$tmp1 || !$tmp2 || !$tmp3) {
				$output .= '<h2>Data Directories (Absolute Server Path)</h2>';
			}
			if(!$tmp1) { $output .= addslashes($this->roomDir)."\n"; }
			if(!$tmp2) { $output .= addslashes($this->dataDir)."\n"; }
			if(!$tmp3) { $output .= addslashes($this->dataDir . 'tmp/')."\n"; }
			if(!$tmp4 || !$tmp5 || !$tmp6 || !$tmp7) {
				$output .= '<h2>File Directories (Relative Web Path)</h2>';
			}
			if(!$tmp4) { $output .= addslashes($this->includeDir . 'files/')."\n"; }
			if(!$tmp5) { $output .= addslashes($this->includeDir . 'files/attachments/')."\n"; }
			if(!$tmp6) { $output .= addslashes($this->includeDir . 'files/avatars/')."\n"; }
			if(!$tmp7) { $output .= addslashes($this->includeDir . 'files/thumb')."\n"; }
			return nl2br(trim($output));
		} else {
			return FALSE;
		}
	}

	private function initDataFiles() {

		define('MESSAGES_LOC', $this->roomDir . base64_encode($this->mySession('current_room')).'.txt');
		define('TOPICL', $this->roomDir . 'topic_'.base64_encode($this->mySession('current_room')).'.txt');
		define('ROOMS_DEF', $this->roomDir . 'def_'.base64_encode($this->mySession('current_room')).'.txt');
		define('MESSAGES_HIDDEN', $this->roomDir . 'hidden_'.base64_encode($this->mySession('current_room')).'.txt');
		define('USERL', $this->dataDir . 'users.txt');
		define('MODL', $this->dataDir . 'mods.txt');
		define('MUTEDL', $this->dataDir . 'muted.txt');
		define('BANNEDL', $this->dataDir . 'bans.txt');
		define('EVENTL', $this->dataDir . 'events.txt');
		define('ROOMS_LASTMOD', $this->dataDir . 'rooms_lastmod.txt');

		if(!file_exists(USERL)) { file_put_contents(USERL, ''); }
		if(!file_exists(MODL)) { file_put_contents(MODL, ''); }
		if(!file_exists(MUTEDL)) { file_put_contents(MUTEDL, ''); }
		if(!file_exists(BANNEDL)) { file_put_contents(BANNEDL, ''); }
		if(!file_exists(EVENTL)) { file_put_contents(EVENTL, ''); }
		if(!file_exists(MESSAGES_LOC)) { file_put_contents(MESSAGES_LOC, time().'|*'.base64_encode('Room').'| has been created.'."\n"); }
		if(!file_exists(TOPICL)) { file_put_contents(TOPICL, ''); }
		if(!file_exists(ROOMS_DEF)) { file_put_contents(ROOMS_DEF, '0||||'); }
		if(!file_exists(ROOMS_LASTMOD)) { file_put_contents(ROOMS_LASTMOD, ''); }
		if(!file_exists(MESSAGES_HIDDEN)) { file_put_contents(MESSAGES_HIDDEN, ''); }

		$this->modList = file_get_contents(MODL);
		$this->mutedList = file_get_contents(MUTEDL);
		$this->msgList = file_get_contents(MESSAGES_LOC);
		$this->hiddenMsgList = file_get_contents(MESSAGES_HIDDEN);
		$this->userList = file_get_contents(USERL);
		$this->topic = file_get_contents(TOPICL);
		$this->bannedList = file_get_contents(BANNEDL);
		$this->eventList = file_get_contents(EVENTL);

		return TRUE;
	}

	private function getPing($name) {
		$src = $this->dataDir . 'tmp/' . base64_encode($name);
		if(file_exists($src)) {
			return filemtime($src);
		} else {
			return 0;
		}
	}

	private function hasPermission($id, $skip_msg = NULL) {
		$level = '';
		$tags = array();

		if($this->isMasterMod === TRUE) {
			$level = 'MMOD';
		} elseif($this->isMod === TRUE) {
			$level = 'MOD';
		} elseif($this->mySession('cname') && $this->isCertifiedUser) {
			$level = 'CUSER';
		} elseif($this->mySession('cname') && !$this->uData[5]) {
			$level = 'USER';
		} else {
			$level = 'GUEST';
		}
		
		$tags = explode(' ', constant('PERM_'.$id));

		if(in_array($level, $tags)) {
			return TRUE;
		} else {
			if($skip_msg == NULL) { echo 'You do not have permission to perform this action!'; }
			return FALSE;
		}
	}

	private function writeFile($file, $content, $mode, $allow_empty_content = NULL) {
		if(strlen(trim($content)) > 0 || $allow_empty_content) {
			$handle = fopen($file, $mode);
			while (!flock($handle, LOCK_EX | LOCK_NB)) {
				sleep(1);
			}
			fwrite($handle, $content);
			fflush($handle);
			flock($handle, LOCK_UN);

			fclose($handle);
		}
	}

	private function getMuted($name) {
		preg_match_all('/^'.base64_encode($name).' ([0-9]+)/im', $this->mutedList, $matches);
		if(isset($matches[1][0]) && $this->hasData(trim($name))) {
			if(time() < $matches[1][0] || $matches[1][0] == 0) {
				return $matches[1][0];
			} else {
				return FALSE;
			}
		}
		else
			return FALSE;
	}

	private function getBanned($name) {
		preg_match_all('/^'.base64_encode($name).' ([0-9]+)/im', $this->bannedList, $matches);
		if(isset($matches[1][0]) && $this->hasData(trim($name))) {
			if(time() < $matches[1][0] || $matches[1][0] == 0) {
				return $matches[1][0];
			} else {
				return FALSE;
			}
		}
		else
			return FALSE;
	}
	
	private function getMod($name) {
		preg_match_all('/^('.base64_encode($name).')/im', $this->modList, $matches);
		if(isset($matches[1][0]) && $this->hasData(trim($name)))
			return $matches[1][0];
		else
			return FALSE;
	}

	private function pError($error) {
		if($this->hasData($error)) {
			return $this->pTemplate('wcchat.error_msg', array('ERR' => $error));
		} else {
			return '';
		}
	}

	private function userMatch($name, $v = NULL, $return_match = NULL) {

		if($v !== NULL) {
			list($tmp, $tmp2) = explode('|', $v, 2);
			if($tmp == base64_encode($name)) {
				return TRUE;
			} else {
				return FALSE;
			}
		} else {
			if(preg_match('/^'.base64_encode($name).'\|/im', trim($this->userList))) {
				if($return_match !== NULL) {
					preg_match_all('/^('.base64_encode($name).')\|(.*)\|([0-9]+)\|([0-9]+)\|([0-9+])/im', trim($this->userList), $matches);
					return array(
						(isset($matches[1][0]) ? $matches[1][0] : ''),
						(isset($matches[2][0]) ? $matches[2][0] : ''),
						(isset($matches[3][0]) ? $matches[3][0] : 0),
						(isset($matches[4][0]) ? $matches[4][0] : 0),
						(isset($matches[5][0]) ? $matches[5][0] : 0)
					);
				} else {
					return TRUE;
				}
			} else {
				if($return_match === NULL) {
					return FALSE;
				} else {
					return array('', '', 0, 0, 0);
				}
			}
		}
	}

	private function userData($forced_name = NULL) {
		$name = (isset($forced_name) ? $forced_name : $this->name);

		list($name, $data, $time, $time2, $status) = $this->userMatch($name, NULL, '1');

		if(isset($data)) {
			if(strlen($data) > 0 && strpos(base64_decode($data), '|') !== FALSE) {
				list($av, $email, $lnk, $tmz, $ampm, $pass) = explode('|', base64_decode($data));
				return array(base64_decode($av), base64_decode($email), base64_decode($lnk), $tmz, $ampm, $pass, $time, $time2, $status);
			}
		}

		return array('', '', '', '0', '0', '', 0, 0, 0);
	}

	private function userLAct($forced_name = NULL) {
	   	$time2 = 0;

		if($forced_name != NULL) {
			list($name, $data, $time, $time2, $status) = $this->userMatch($forced_name, NULL, '1');
		} else {
			$time2 = $this->uData[7];
		}
		return $time2;
	}

	private function handleLastRead($mode, $sufix = NULL) {

		$id = 'lastread'.($sufix !== NULL ? '_'.$sufix : '_'.$this->mySession('current_room'));
    		$enc =  str_replace('=', '_', base64_encode($id));
		switch($mode) {
			case 'store':
				$_SESSION[$id] = time();
				setcookie($enc, time(), time()+(86400*365), '/');
			break;
			case 'read':
				if($this->myCookie($enc)) { $_SESSION[$id] = $this->myCookie($enc); }
				return $this->mySession($id);
			break;
		}
	}

	private function updateUser($contents, $mode = NULL, $value = NULL) {

		$list = explode("\n", trim($contents));
		foreach($list as $k => $v) {
			if(trim($v) && $this->name) {
				if($this->userMatch($this->name, $v) !== FALSE) {
					if(isset($mode)) {
						list($usr, $udata, $f, $l, $s) = explode('|', trim($v));
					}
					switch($mode) {
						case 'update_status':							
							$contents = str_replace(trim($v), $usr.'|'.$udata.'|'.$f.'|'.$l.'|'.$value, $contents);
						break;
						case 'lurker_join':							
							if($f == '0') {
								$contents = str_replace(
									trim($v),
									$usr.'|'.$udata.'|'.time().'|'.time().'|1',
									$contents
								);
							}
						break;
						case 'new_message':
							$contents = str_replace(
								trim($v),
									$usr.'|'.
									$udata.'|'.
									$f.'|'.
									time().'|'.
									$s, 
								$contents);
						break;
						case 'lurker_visit':
							if($f == '0' || ($f != '0' && $this->hasProfileAccess)) {
								$contents = str_replace(trim($v), $usr.'|'.$udata.'|'.$f.'|'.time().'|'.$s, $contents);
								$this->writeFile(USERL, $contents, 'w');
							}
						break;
					}
					break;
				}
			}
		}
		return $contents;
	}

	private function idlet($date, $mode = NULL)
	{
		if($mode == NULL)
			$it = $timesec = time()-$date;
		else
			$it = $timesec = $date-time();

		$str = '';
		$ys = 60*60*24*365;

		$year = intval($timesec/$ys);
		if($year >= 1)
			$timesec = $timesec%$ys;

		$month = intval($timesec/2628000);
		if($month >= 1)
			$timesec = $timesec%2628000;
	
		$days = intval($timesec/86400);
		if($days >= 1)
			$timesec = $timesec%86400;

		$hours = intval($timesec/3600);
		if($hours >= 1)
			$timesec = $timesec%3600;

		$minutes = intval($timesec/60);
		if($minutes >= 1)
			$timesec = $timesec%60;

		if($year > 0)
			$str = $year.'Y';
		if($month > 0)
			$str .= " ".$month.'M';
		if($days > 0)
			$str .= " ".$days.'d';
		if($hours > 0)
			$str .= " ".$hours.'h';
		if($minutes > 0 && !$days)
			$str .= " ".$minutes.'m';
		if($it < 60)
			$str = $it."s";

		return(trim($str));
	}

	private function bbcode($data) {
		$search =
			array(
      			'/\[b\](.*?)\[\/b\]/si',
      			'/\[i\](.*?)\[\/i\]/si',
      			'/\[u\](.*?)\[\/u\]/si',
      			'/\[img](.*?)\[\/img\]/si',
      			'/\[imga\|([0-9]+)x([0-9]+)\|([0-9]+)x([0-9]+)\](.*?)\[\/img\]/si',
				'/\[imga\|([0-9]+)x([0-9]+)\|([0-9]+)x([0-9]+)\|tn_([0-9A-Z]+)\](.*?)\[\/img\]/si',
				'/\[img\|([0-9]+)x([0-9]+)\|([0-9]+)x([0-9]+)\](.*?)\[\/img\]/si',
				'/\[img\|([0-9]+)x([0-9]+)\|([0-9]+)x([0-9]+)\|tn_([0-9A-Z]+)\](.*?)\[\/img\]/si',
      			'/\[img\|([0-9]+)\](.*?)\[\/img\]/si',
      			'/\[url\="(.*?)"\](.*?)\[\/url\]/si',
				'/\[attach_(.*)_([0-9a-f]+)_([0-9]+)_([A-Za-z0-9 _\.]+)\]/iU',
				'/https:\/\/www\.youtube\.com\/watch\?v=([0-9a-zA-Z]*)/i',
				'/(?<!href=\"|src=\"|\])((http|ftp)+(s)?:\/\/[^<>\s]+)/i'
   			);

		$down_perm = ($this->hasPermission('ATTACH_DOWN', 'skip_msg') ? TRUE : FALSE);
		$down_alert = ($this->hasPermission('ATTACH_DOWN', 'skip_msg') ? '' : 'onclick="alert(\'Your usergroup does not have permission to download arquives / full size images!\'); return false;"');

		$replace =
			array(
      			'<b>\\1</b>',
      			'<i>\\1</i>',
      			'<u>\\1</u>',
      			'<div style="margin: 10px"><img src="\\1" class="wc_thumb" onload="wc_doscroll()"></div>',

      			'<div style="width: \\3px; text-align: center; font-size: 10px; margin: 10px"><img src="\\5" style="width: \\3px; height: \\4px;" class="wc_thumb" onload="wc_doscroll()"><br><img src="'.INCLUDE_DIR_THEME.'images/attach.png"><a href="'.($down_perm ? '\\5' : '#').'" target="_blank" '.$down_alert.'>\\1 x \\2</a></div>',
				'<div style="width: \\3px; text-align: center; font-size: 10px; margin: 10px"><img src="'.$this->includeDir.'files/thumb/tn_\\5.jpg" class="wc_thumb" onload="wc_doscroll()"><br><img src="'.INCLUDE_DIR_THEME.'images/attach.png"><a href="'.($down_perm ? '\\6' : '#').'" target="_blank" '.$down_alert.'>\\1 x \\2</a></div>',

      			'<div style="width: \\3px; text-align: center; font-size: 10px; margin: 10px"><img src="\\5" style="width: \\3px; height: \\4px;" class="wc_thumb" onload="wc_doscroll()"><br><a href="'.($down_perm ? '\\5' : '#').'" target="_blank" '.$down_alert.'>\\1 x \\2</a></div>',
				'<div style="width: \\3px; text-align: center; font-size: 10px; margin: 10px"><img src="'.$this->includeDir.'files/thumb/tn_\\5.jpg" class="wc_thumb" onload="wc_doscroll()"><br><a href="'.($down_perm ? '\\6' : '#').'" target="_blank" '.$down_alert.'>\\1 x \\2</a></div>',
				'<div style="width: \\1px; text-align: center; font-size: 10px; margin: 10px"><img src="\\2" style="width: \\1px;" class="wc_thumb" onload="wc_doscroll()"><br><a href="'.($down_perm ? '\\2' : '#').'" target="_blank" '.$down_alert.'>Unknown Dimensions</a></div>',
      			'<a href="\\1" target="_blank">\\2</a>',
				'<div style="margin:10px;"><i><img src="'.INCLUDE_DIR_THEME.'images/attach.png"> <a href="'.($down_perm ? 'files/attachments/\\1_\\2_\\4' : '#').'" target="_blank" '.$down_alert.'>\\4</a> <span style="font-size: 10px">(\\3KB)</span></i></div>',
				'<div id="im_\\1"><a href="#" onclick="wc_pop_vid(\'\\1\', '.VIDEO_WIDTH.', '.VIDEO_HEIGHT.'); return false;"><img src="'.INCLUDE_DIR_THEME.'images/video_cover.jpg" class="wc_thumb" style="margin: 10px" onload="wc_doscroll()"></a></div><div id="video_\\1" class="closed"></div>',
				'<a href="\\0" target="_blank" style="font-size:10px;font-family:tahoma">\\0</a>'
   			);

		$output = preg_replace($search, $replace, $data);

		$smilies =
			array(
				'1' => ':)',
				'3' => ':o',
				'2' => ':(',
				'4' => ':|',
				'5' => ':frust:',
				'6' => ':D',
				'7' => ':p',
				'8' => '-_-',
				'10' => ':E',
				'11' => ':mad:',
				'12' => '^_^',
				'13' => ':cry:',
				'14' => ':inoc:',
				'15' => ':z',
				'16' => ':love:',
				'17' => '@_@',
				'18' => ':sweat:',
				'19' => ':ann:',
				'20' => ':susp:',
				'9' => '>_<'
			);


		foreach($smilies as $key => $value)
			$output =
				str_replace(
					$value,
					$this->pTemplate('wcchat.toolbar.smiley.item.parsed',
					array(
						'key' => $key
					)
				),
				$output
			);

		return $output;
	}

	private function initImg($original_image) {

		$source_image = '';
		if(!function_exists('gd_info')) { return FALSE; }

		if(function_exists('exif_imagetype')) {
			$type = exif_imagetype($original_image);
		} else {
			$tmp = @getimagesize($original_image);
			$type = $tmp[2];
		}

		if($type == 1) {
			$source_image = @ImageCreateFromGIF($original_image);
		}

		if($type == 2) {
			$source_image = @ImageCreateFromJPEG($original_image);
		}

		if($type == 3) {
			$source_image = @ImageCreateFromPNG($original_image);
		}

		if($source_image) {
			return $source_image;
		} else {
			return FALSE;
		}
	}

	private function parseImg($s, $attach = NULL) {

		$source = (is_array($s) ? $s[1] : $s);

		$iname = str_replace('https://', 'http://', $source);
		$image = $this->initImg($iname);

		if($image) {
			$w = imagesx($image);
			$h = imagesy($image);
		} else {
			$w = $h = 0;
		}

		$nw = $nh = 0;

		if($w && $h) {
			if($h > IMAGE_MAX_DSP_DIM || $w > IMAGE_MAX_DSP_DIM) {
				if($w >= $h) {
					$nh = intval(($h * IMAGE_MAX_DSP_DIM) / $w);
					$nw = IMAGE_MAX_DSP_DIM;
				} else {
					$nw = intval(($w * IMAGE_MAX_DSP_DIM) / $h);
					$nh = IMAGE_MAX_DSP_DIM;
				}

				$target = 'files/thumb/tn_' . strtoupper(dechex(crc32($iname))) . '.jpg';
				if(GEN_REM_THUMB) {
					if($this->thumbnailCreateMed($iname, $image, $w, $h, __DIR__ . '/' .$target, IMAGE_MAX_DSP_DIM)) {
						return '[IMG'.($attach != NULL ? 'A' : '').'|'.$w.'x'.$h.'|'.$nw.'x'.$nh.'|tn_'.strtoupper(dechex(crc32($iname))).']' . $source . '[/IMG]';
					} else {
						return '[IMG'.($attach != NULL ? 'A' : '').'|'.$w.'x'.$h.'|'.$nw.'x'.$nh.']'.$source.'[/IMG]';
					}
				} else {
					return '[IMG'.($attach != NULL ? 'A' : '').'|'.$w.'x'.$h.'|'.$nw.'x'.$nh.']'.$source.'[/IMG]';
				}
			} else {
				return '[IMG]'.$source.'[/IMG]';
			}
		} else {
				return '[IMG|'.IMAGE_AUTO_RESIZE_UNKN.']'.$source.'[/IMG]';
		}
	}

	private function writeEvent($omode, $target) {
		if((time()-filemtime(EVENTL)) > 60) { $mode = 'w'; } else { $mode = 'a'; }
		switch($omode) {
			case 'ignore':
				$towrite = time().'|'.base64_encode($this->name).'|'.base64_encode($target).'|<b>'.$this->name.'</b> is ignoring you.'."\n";
			break;
				case 'unignore':
				$towrite = time().'|'.base64_encode($this->name).'|'.base64_encode($target).'|<b>'.$this->name.'</b> is no longer ignoring you.'."\n";
			break;
		}
		$this->writeFile(EVENTL, $towrite, $mode);
	}

	private function thumbnailCreateMed ($original_image, $source_image, $w, $h, $target_image, $thumbsize)
	{
		if(!$source_image || !function_exists('gd_info')) { return FALSE; }

		if($w > $thumbsize OR $h > $thumbsize) {
			if($w >= $h) {

				$sizey = $thumbsize * $h;
				$thumbsizey = intval($sizey / $w);
				$temp_image = @ImageCreateTrueColor($thumbsize, $thumbsizey);
				$thw = $thumbsize; $thy = $thumbsizey;
			} else {
				$sizew = $thumbsize * $w;
				$thumbsizew = intval($sizew / $h);
				$temp_image = @ImageCreateTrueColor($thumbsizew, $thumbsize);
				$thw = $thumbsizew; $thy = $thumbsize;
			}
		} else {
			$thw = $w; $thy = $h;
			$temp_image = @ImageCreateTrueColor($thw, $thy);
		}

		@ImageCopyResampled($temp_image, $source_image, 0, 0, 0, 0, $thw, $thy, $w, $h);

		@ImageJPEG($temp_image, $target_image, 80);

		@ImageDestroy($temp_image);

		if (!file_exists($target_image)) { return false; } else { return true; }
	}

	function thumbnail_create ($original_image, $target_image, $thumbsize) {

		$source_image = $this->initImg($original_image);

		if(!$source_image || !function_exists('gd_info')) { return FALSE; }

		$w = imagesx($source_image);
		$h = imagesx($source_image);

		// Set offset for the picture cropping (depending on orientation)
		if ($w > $h) {
			$smallestdimension = $h;
			$widthoffset = ceil(($w - $h) / 2);
			$heightoffset = 0;
		} else {
			$smallestdimension = $w;
			$widthoffset = 0;
			$heightoffset = ceil(($h - $w) / 2);
		}

		// Create a temporary image for cropping original (using smallest side for dimensions)
		$temp_image1 = @ImageCreateTrueColor($smallestdimension, $smallestdimension);
		// Resize the image to smallest dimension (centered using offset values from above)
		@ImageCopyResampled($temp_image1, $source_image, 0, 0, $widthoffset, $heightoffset, $smallestdimension, $smallestdimension, $smallestdimension, $smallestdimension);
		// Create thumbnail and save

		@ImageJPEG($temp_image1, $target_image, 90);

		// Create a temporary new image for the final thumbnail
		$temp_image2 = @ImageCreateTrueColor($thumbsize, $thumbsize);
		// Resize this image to the given thumbnail size
		@ImageCopyResampled($temp_image2, $temp_image1, 0, 0, 0, 0, $thumbsize, $thumbsize, $smallestdimension, $smallestdimension);
		// Create thumbnail and save

		@ImageJPEG($temp_image2, $target_image, 90);

		// Delete temporary images
		@ImageDestroy($temp_image1);
		@ImageDestroy($temp_image2);
		// Return status (check for file existance)
		if (!file_exists($target_image)) { return false; } else { return true; }
	}

	private function parseTopicContainer() {
		$this->topic = file_get_contents(TOPICL);

		$topic_con = nl2br($this->bbcode($this->topic));
		if(strpos($this->topic, '[**]') !== FALSE) {
			list($v1, $v2) = explode('[**]', $this->topic);
			$topic_con = $this->pTemplate(
				'wcchat.topic.box.partial',
				array(
					'TOPIC_PARTIAL' => nl2br($this->bbcode($v1)),
					'TOPIC_FULL' => nl2br($this->bbcode(str_replace('[**]', '', $this->topic)))
				)
			);
		}

		return $this->pTemplate(
			'wcchat.topic.inner',
			array(
				'CURRENT_ROOM' => $this->mySession('current_room'),
				'TOPIC' =>
					$this->pTemplate(
						'wcchat.topic.box',
						array(
							'TOPIC_CON' => ($this->topic ? $topic_con : 'No topic set.'),
							'TOPIC_TXT' => ($this->topic ? stripslashes($this->topic) : ''),
							'TOPIC_EDIT_BT' => $this->pTemplate(
								'wcchat.topic.edit_bt',
								array('OFF' => ($this->myCookie('hide_edit') == 1 ? '_off' : '')),
								$this->hasPermission('TOPIC_E', 'skip_msg')
							),
							'BBCODE' => $this->pTemplate(
								'wcchat.toolbar.bbcode',
								array(
									
									'SMILIES' => $this->smiley('wc_topic_txt'),
									'FIELD' => 'wc_topic_txt',
									'ATTACHMENT_UPLOADS' => ''
								)
							)
						)
					)
			)
		);
	}

	private function parseMsg($lines, $lastread, $action, $older_index = NULL) {
		$index = 0;
		$output = $new = '';
		$previous = $skip_new = 0;
		$today_date = gmdate('d-M', time()+($this->userTimezone * 3600));

		$avatar_array = array();
		if($action != 'SEND') {
			if(trim($this->userList)) {
				$av_lines = explode("\n", trim($this->userList));
				foreach($av_lines as $k => $v) {
					list($tmp1, $tmp2, $tmp3, $tmp4) = explode('|', trim($v));
					list($tmp5, $tmp6) = explode('|', base64_decode($tmp2), 2);
					$avatar_array[base64_decode($tmp1)] = base64_decode($tmp5);
				}
			}
		} else {
			$avatar_array[$this->name] = $this->uData[0];
		}

		if(!$this->hasRoomPermission($this->mySession('current_room'), 'R')) {
			echo 'You do not have permission to access this room!';
			die();
		}
	
		krsort($lines);
		foreach($lines as $k => $v) {
			list($time, $user, $msg) = explode('|', trim($v), 3);
			$pm_target = FALSE;
			if(strpos($user, '-') !== FALSE) { list($pm_target, $nuser) = explode('-', $user); $user = $nuser; }

			$self = FALSE; $hidden = FALSE;

			if(strpos($time, '*') !== FALSE) { $time = str_replace('*', '', $time); $hidden = TRUE; }

			if(preg_match('/^\*/', $user)) { $self = TRUE; $user = trim($user, '*'); }
			$time_date = gmdate('d-M', $time+($this->userTimezone * 3600));
			if($this->myGet('all') != 'ALL' && $time <= $lastread && !$older_index) { break; }
			if((($time > $lastread || $this->myGet('all') == 'ALL') && $index < CHAT_DSP_BUFFER) || ($older_index && ($index) >= $older_index && $index <= ($older_index+CHAT_OLDER_MSG_STEP))) {

				if($time < $this->mySession('start_point_'.$this->mySession('current_room'))) {
					break;
				}

				if($this->myGet('all') != 'ALL' && !$older_index && base64_decode($user) == $this->name && $action != 'SEND') {
					$index++; continue;
				}

				$new = '';
				if($this->myGet('all') == 'ALL' && !$skip_new) {
					if($previous) {
						if($time < $lastread && $previous > $lastread) {
							$new = $this->pTemplate('wcchat.posts.new_msg_separator');
							$skip_new = 1;
						}
					} else {
						$previous = $time;
					}
				}

				$unique_id = (!$self ? ($time.'|'.$user) : ($time.'|*'.$user));

				if(!$this->myCookie('ign_'.$user)) {
					if(!$self) {
						if($pm_target === FALSE || ((base64_decode($pm_target) == $this->name || base64_decode($user) == $this->name) && $this->hasProfileAccess && $pm_target !== FALSE)) {
							$output = $this->pTemplate(
								'wcchat.posts.normal',
								array(
									'PM_SUFIX' => ($pm_target !== FALSE ? '_pm' : ''),
									'PM_TAG' => $this->pTemplate('wcchat.posts.normal.pm_tag', '', $pm_target !== FALSE),
									'STYLE' => ($this->myCookie('hide_time') ? 'display:none' : 'display:inline'),
									'TIMESTAMP' => gmdate((($this->hFormat == '1') ? 'H:i': 'g:i a'), $time + ($this->userTimezone * 3600)).($time_date != $today_date ? ' '.$time_date : ''),
									'POPULATE_START' => $this->pTemplate('wcchat.posts.normal.populate_start', array('USER' => addslashes(base64_decode($user))), base64_decode($user) != $this->name),
									'USER' => base64_decode($user),
									'POPULATE_END' => $this->pTemplate('wcchat.posts.normal.populate_end', '', base64_decode($user) != $this->name),
									'PM_TARGET' => (
											(base64_decode($pm_target) != $this->name) ?
											$this->pTemplate('wcchat.posts.normal.pm_target', array('TITLE' => base64_decode($pm_target)), $pm_target !== FALSE) :
											$this->pTemplate('wcchat.posts.normal.pm_target.self', array('TITLE' => base64_decode($pm_target)), $pm_target !== FALSE)
										),
									'MSG' => $this->pTemplate('wcchat.posts.hidden', '', $hidden, $msg),
									'ID' => $unique_id,
									'HIDE_ICON' => $this->pTemplate('wcchat.posts.hide_icon', array('REVERSE' => ($hidden ? '_r' : ''), 'ID' => $unique_id, 'OFF' => ($this->myCookie('hide_edit') == 1 ? '_off' : '')), $this->hasPermission(($hidden ? 'MSG_UNHIDE' : 'MSG_HIDE'), 'skip_msg')),
									'AVATAR' => ($avatar_array[base64_decode($user)] ? $this->includeDir . 'files/avatars/'.$avatar_array[base64_decode($user)] : INCLUDE_DIR_THEME . DEFAULT_AVATAR ),
									'WIDTH' => $this->pTemplate('wcchat.posts.normal.width', array('WIDTH' => $this->avResize_w), $this->avResize_w > 0)
								)
							).$new.$output;
						}
					} else {
						$output = $this->pTemplate(
							'wcchat.posts.self',
							array(
								'STYLE' => ($this->myCookie('hide_time') ? 'display: none' : 'dislay:inline'),
								'TIMESTAMP' => 
									gmdate(
										(($this->hFormat == '1') ? 'H:i': 'g:i a'), 
										$time + ($this->userTimezone * 3600)
									).
									($time_date != $today_date ? ' '.$time_date : ''),
								'USER' => base64_decode($user),
								'MSG' => $this->pTemplate('wcchat.posts.hidden', '', $hidden, $msg),
								'ID' => $unique_id,
								'HIDE_ICON' => $this->pTemplate('wcchat.posts.hide_icon', array('REVERSE' => ($hidden ? '_r' : ''), 'ID' => $unique_id, 'OFF' => ($this->myCookie('hide_edit') == 1 ? '_off' : '')), $this->hasPermission(($hidden ? 'MSG_UNHIDE' : 'MSG_HIDE'), 'skip_msg'))
							)
						).$new.$output;
					}
				}
			}
			$index++;
			if(($index >= CHAT_DSP_BUFFER && !$older_index) || ($older_index && $index > ($older_index+CHAT_OLDER_MSG_STEP))) { break; }
		}

		return array($output, $index);
	}

	private function parseMsgE($lines, $lastread, $action) {
		$output = '';
		$today_date = gmdate('d-M', time()+($this->userTimezone * 3600));

		krsort($lines);
		foreach($lines as $k => $v) {
			$tar_user = '';
			if(substr_count($v, '|') == 3) {
				list($time, $user, $tar_user, $msg) = explode('|', trim($v), 4);
				$tar_user = base64_decode($tar_user);
			} else {
              		list($time, $user, $msg) = explode('|', trim($v), 3);
			}
			if(($tar_user && $tar_user == $this->mySession('cname')) || !$tar_user) {
				$time_date = gmdate('d-M', $time+($this->userTimezone * 3600));
				if($time <= $lastread || (!$lastread && $action != 'SEND') || (time()-$time) > (intval(REFRESH_DELAY/1000)*2)) { break; }

				if(base64_decode($user) == $this->name && $action != 'SEND') { continue; }

				if($time > $lastread) { 
					$output = $this->pTemplate(
						'wcchat.posts.event',
						array(
							'STYLE' => ($this->myCookie('hide_time') ? 'display: none' : 'dislay:inline'),
							'TIMESTAMP' => 
								gmdate(
									(($this->hFormat == '1') ? 'H:i': 'g:i a'), 
									$time + ($this->userTimezone * 3600)
								).
								($time_date != $today_date ? ' '.$time_date : ''),
							'MSG' => $msg
						)
					).$output;
				}
			}
		}
		return $output;
	}

	private function uList($visit = NULL) {

		if($this->hasProfileAccess) {
			touch($this->dataDir . 'tmp/' . base64_encode($this->name));
		}

		if($this->isBanned !== FALSE) { return 'You are banned!'; }
		if(!$this->hasPermission('USER_LIST', 'skip_msg')) { return 'Can\'t display users.'; }
 
		if($this->myGET('ilmod') != 'ignore_lastmod') {
			$online_lastmod = filemtime(USERL);
			// if((time()-$online_lastmod) >= IDLE_START) { return; }
		}
		$_on = $_off = $_lurker = array();
		$uid = $this->name;

		$contents = $this->userList;
		$changes = FALSE;

		if($visit != NULL && $this->hasProfileAccess) {
			$contents = $this->updateUser($contents, 'update_status', 0);
			$changes = TRUE;
		}

		if($this->myGet('join') == '1') {
			if($this->userMatch($uid) === FALSE) {
				$contents .= "\n".base64_encode($uid).'|'.$this->userDataString.'|'.time().'|'.time().'|0';
			} else {
				$contents = $this->updateUser($contents, 'lurker_join');
			}
			$contents = $this->updateUser($contents, 'update_status', 1);
			$changes = TRUE;
		}

		if($this->myGet('new') == '1') {
			$contents = $this->updateUser($contents, 'new_message');
			$changes = TRUE;
		}

		if($changes) {
			$this->writeFile(USERL, trim($contents), 'w');
		}

		if(trim($contents)) {
			$lines = explode("\n", trim($contents));
			foreach($lines as $k => $v) {
				if(trim($v)) {
					list($usr, $udata, $f, $l, $s) = explode('|', trim($v));
					$usr = ($usr ? base64_decode($usr) : '');

					$mod_icon = $this->pTemplate(
						'wcchat.users.item.icon_moderator', 
						'',
						($this->getMod($usr) !== FALSE && strlen(trim($usr)) > 0)
					);

					$muted_icon = '';
					$ismuted = $this->getMuted($usr);
					if($ismuted !== FALSE && strlen(trim($usr)) > 0) {
						$muted_icon = $this->pTemplate('wcchat.users.item.icon_muted');
					}
					if($this->myCookie('ign_'.$usr)) {
						$muted_icon = $this->pTemplate('wcchat.users.item.icon_ignored');
					}

					if(strlen($udata) > 0 && strpos(base64_decode($udata), '|') !== FALSE) {
						list($tmp1, $tmp2, $tmp3, $tmp4, $tmp5, $tmp6) = explode('|', base64_decode($udata));
						$av = ($tmp1 ? $this->includeDir . 'files/avatars/' . base64_decode($tmp1) : INCLUDE_DIR_THEME.DEFAULT_AVATAR);
						$ulink = ($tmp3 ? (preg_match('#^(http|ftp)#i', base64_decode($tmp3)) ? base64_decode($tmp3) : 'http://'.base64_decode($tmp3)) : '');
					} else {
						$ulink = '';
						$av = (DEFAULT_AVATAR ? INCLUDE_DIR_THEME.DEFAULT_AVATAR : '');
					}

					$name_style = '';
					$isbanned = $this->getBanned($usr);
					if($isbanned !== FALSE) {
						$ulink = ''; $av = (DEFAULT_AVATAR ? INCLUDE_DIR_THEME.DEFAULT_AVATAR : '');
						$name_style = ' style="text-decoration: line-through;"';
					}

					$status_title = '';
					$last_ping = $this->getPing($usr);
					switch($s) {
						case '1':
							if((time()-$l) >= IDLE_START) {
								$status_title = 'Idle';
							} else {
								$status_title = 'Available';
							}
						break;
						case '2': $status_title = 'Do Not Disturb'; break;
						default: $status_title = 'Not Joined';
					}

					$edit_perm = FALSE;
					if($this->hasPermission('USER_E', 'skip_msg')) {
						$edit_perm = TRUE;
					}

					$mod_perm = FALSE;
					if($this->hasPermission('MOD', 'skip_msg') || $this->hasPermission('UNMOD', 'skip_msg') || $this->hasPermission('BAN', 'skip_msg') || $this->hasPermission('UNBAN', 'skip_msg') || $this->hasPermission('MUTE', 'skip_msg') || $this->hasPermission('UNMUTE', 'skip_msg')) {
						$mod_perm = TRUE;
					}

					if($f != '0') {
						if((time()-$last_ping) < OFFLINE_PING) { 

							if(strpos($v, base64_encode($uid)) === FALSE) { $boldi = $bolde = ''; } else { $boldi = '<b>'; $bolde = '</b>'; }
							$joined_status_class = 'joined_on';
					
							if(((time()-$l) >= IDLE_START)) { $joined_status_class = 'joined_idle'; }
							if($s == 2) { $joined_status_class = 'joined_na'; }		

							$_on[$usr] = $this->pTemplate(
								'wcchat.users.item',
								array(
									'WIDTH' => $this->pTemplate('wcchat.users.item.width', array('WIDTH' => $this->avResize_w), $this->avResize_w > 0),
									'AVATAR' => $av,
									'NAME_STYLE' => $name_style,
									'PREFIX' => $boldi.$usr.$bolde.$mod_icon.$muted_icon,
									'LINK' => $this->pTemplate('wcchat.users.item.link', array('LINK' => $ulink), $ulink),
									'IDLE' => $this->pTemplate('wcchat.users.item.idle_var', array('IDLE' => $this->idlet($l)), ((time()-$l) >= IDLE_START && $s != 2)),
									'JOINED_CLASS' => (intval($s) ? $joined_status_class : ''),
									'STATUS_TITLE' => $status_title,
									'EDIT_BT' => $this->pTemplate('wcchat.users.item.edit_bt', array('ID' => base64_encode($usr), 'OFF' => ($this->myCookie('hide_edit') == 1 ? '_off' : '')), ($edit_perm || $mod_perm)),
									'EDIT_FORM' => $this->pTemplate(
										'wcchat.users.item.edit_form',
										array(
											'ID' => base64_encode($usr),
											'MODERATOR' => $this->pTemplate(
												'wcchat.users.item.edit_form.moderator',
												array(
													'MOD_CHECKED' => ($mod_icon ? 'CHECKED' : ''),
													'ID' => base64_encode($usr)
												),
												$this->hasPermission('MOD', 'skip_msg') || $this->hasPermission('UNMOD', 'skip_msg')
											),
											'MUTED' => $this->pTemplate(
												'wcchat.users.item.edit_form.muted',
												array(
													'MUTED_CHECKED' => ($ismuted !== FALSE ? 'CHECKED' : ''),
													'MUTED_TIME' => (($ismuted !== FALSE && $ismuted != 0) ? intval(abs((time()-$ismuted)/60)) : ''),
													'ID' => base64_encode($usr)
												),
												$this->hasPermission('MUTE', 'skip_msg') || $this->hasPermission('UNMUTE', 'skip_msg')
											),
											'BANNED' => $this->pTemplate(
												'wcchat.users.item.edit_form.banned',
												array(
													'BANNED_CHECKED' => ($isbanned !== FALSE ? 'CHECKED' : ''),
													'BANNED_TIME' => (($isbanned !== FALSE && $isbanned != 0) ? intval(abs((time()-$isbanned)/60)) : ''),
													'ID' => base64_encode($usr)
												),
												$this->hasPermission('BAN', 'skip_msg') || $this->hasPermission('UNBAN', 'skip_msg')
											),
											'MOD_NOPERM' => (!$mod_perm ? 'No Permission!' : ''),
											'PROFILE_DATA' => $this->pTemplate(
												'wcchat.users.item.edit_form.profile_data',
												array(
													'NAME' => $usr,
													'EMAIL' => base64_decode($tmp2),
													'WEB' => base64_decode($tmp3),
													'DIS_AV' => (trim($tmp1) ? '' : 'DISABLED'),
													'DIS_PASS' => (trim($tmp6) ? '' : 'DISABLED'),
													'ID' => base64_encode($usr)
												),
												$edit_perm,
												'No permission!'
											),
											'NAME' => $usr
										),
										$edit_perm || $mod_perm
									)
								)
							);

						} else {
							$_off[$usr] = $this->pTemplate(
								'wcchat.users.item',
								array(
									'WIDTH' => $this->pTemplate('wcchat.users.item.width', array('WIDTH' => $this->avResize_w), $this->avResize_w > 0),
									'AVATAR' => $av,
									'NAME_STYLE' => $name_style,
									'PREFIX' => $usr.$mod_icon.$muted_icon,
									'LINK' => $this->pTemplate('wcchat.users.item.link', array('LINK' => $ulink), $ulink),
									'IDLE' => $this->pTemplate('wcchat.users.item.idle_var', array('IDLE' => $this->idlet($l))),
									'JOINED_CLASS' => '',
									'STATUS_TITLE' => '',
									'EDIT_BT' => $this->pTemplate('wcchat.users.item.edit_bt', array('ID' => base64_encode($usr), 'OFF' => ($this->myCookie('hide_edit') == 1 ? '_off' : '')), ($edit_perm || $mod_perm)),
									'EDIT_FORM' => $this->pTemplate(
										'wcchat.users.item.edit_form',
										array(
											'ID' => base64_encode($usr),
											'MODERATOR' => $this->pTemplate(
												'wcchat.users.item.edit_form.moderator',
												array(
													'MOD_CHECKED' => ($mod_icon ? 'CHECKED' : ''),
													'ID' => base64_encode($usr)
												),
												$this->hasPermission('MOD', 'skip_msg') || $this->hasPermission('UNMOD', 'skip_msg')
											),
											'MUTED' => $this->pTemplate(
												'wcchat.users.item.edit_form.muted',
												array(
													'MUTED_CHECKED' => ($ismuted !== FALSE ? 'CHECKED' : ''),
													'MUTED_TIME' => (($ismuted !== FALSE && $ismuted != 0) ? intval(abs((time()-$ismuted)/60)) : ''),
													'ID' => base64_encode($usr)
												),
												$this->hasPermission('MUTE', 'skip_msg') || $this->hasPermission('UNMUTE', 'skip_msg')
											),
											'BANNED' => $this->pTemplate(
												'wcchat.users.item.edit_form.banned',
												array(
													'BANNED_CHECKED' => ($isbanned !== FALSE ? 'CHECKED' : ''),
													'BANNED_TIME' => (($isbanned !== FALSE && $isbanned != 0) ? intval(abs((time()-$isbanned)/60)) : ''),
													'ID' => base64_encode($usr)
												),
												$this->hasPermission('BAN', 'skip_msg') || $this->hasPermission('UNBAN', 'skip_msg')
											),
											'MOD_NOPERM' => (!$mod_perm ? 'No Permission!' : ''),
											'PROFILE_DATA' => $this->pTemplate(
												'wcchat.users.item.edit_form.profile_data',
												array(
													'NAME' => $usr,
													'EMAIL' => base64_decode($tmp2),
													'WEB' => base64_decode($tmp3),
													'DIS_AV' => (trim($tmp1) ? '' : 'DISABLED'),
													'DIS_PASS' => (trim($tmp6) ? '' : 'DISABLED'),
													'ID' => base64_encode($usr)
												),
												$edit_perm,
												'No permission!'
											),
											'NAME' => $usr
										),
										$edit_perm || $mod_perm
									)
								)
							);
						}
					} elseif(LIST_GUESTS === TRUE) {
						$_lurker[$usr] = $this->pTemplate('wcchat.users.guests.item', array('USER' => $usr, 'IDLE' => $this->idlet($l)));
					}
				}
			}
		}
		$on = $off = $lurker = '';
		ksort($_on); ksort($_off); ksort($_lurker);
		foreach($_on as $k => $v) $on .= $v;
		foreach($_off as $k => $v) $off .= $v;
		foreach($_lurker as $k => $v) $lurker .= $v;

		return 
			$this->pTemplate(
				'wcchat.users.inner',
				array(
					'JOINED' => (
						$on ? 
						$this->pTemplate('wcchat.users.joined', array('USERS' => $on)) : 
						$this->pTemplate('wcchat.users.joined.void')
					),
					'OFFLINE' => $this->pTemplate('wcchat.users.offline', array('USERS' => $off), $off),
					'GUESTS' => $this->pTemplate('wcchat.users.guests', array('USERS' => $lurker), $lurker),
				)
			);
	}

	private function checkTopicChanges() {
		$lastmod_t = filemtime(TOPICL);
		$lastread_t = $this->handleLastRead('read', 'topic_'.$this->mySession('current_room'));

		if($lastmod_t > $lastread_t || $this->myGet('reload') == '1') {
			$this->handleLastRead('store', 'topic_'.$this->mySession('current_room'));
			$t = $this->topic;
			return $this->parseTopicContainer();
		}
	}

	private function refreshRooms() {
		$lastread = $this->handleLastRead('read', 'rooms_lastread');
		$lastmod = filemtime(ROOMS_LASTMOD);
		if($lastread < $lastmod) {
			$this->handleLastRead('store', 'rooms_lastread');
			return $this->rList();
		}
	}

	private function checkHiddenMsg() {
		return trim($this->hiddenMsgList, ' ');
	}

	private function hasRoomPermission($room_name, $mode) {
		list($perm, $t1, $t2, $t3, $t4) = explode('|', file_get_contents($this->roomDir . 'def_'.base64_encode($room_name).'.txt'));
		$permission = FALSE;
		$target = (($mode == 'W') ? $perm : $t1);
		switch($target) {
			case '1':
				if($this->isMasterMod === TRUE) { $permission = TRUE; }
			break;
			case '2':
				if($this->isMod === TRUE) { $permission = TRUE; }
			break;
			case '3':
				if($this->mySession('cname') && $this->isCertifiedUser === TRUE) { $permission = TRUE; }
			break;
			case '4':
				if($this->mySession('cname') && !$this->uData[5]) {
					$permission = TRUE;
				}
			break;
			default: $permission = TRUE;
		}
		return $permission;
	}

	private function updateMsgOnceE() {
		if(!$this->hasPermission('READ_MSG', 'skip_msg')) { return 'Can\'t display messages.'; }
		$output_e = '';
		$lastmod = filemtime(EVENTL);
		$lastread = $this->handleLastRead('read', 'events_');

		if($lastmod > $lastread && $this->myGet('all') != 'ALL') {
			$this->handleLastRead('store', 'events_');

			if($this->hasData($this->eventList)) {
				$lines = explode("\n", trim($this->eventList));
				$output_e = $this->parseMsgE($lines, $lastread, 'RECEIVE');
			}
		}
		if($output_e) return $this->bbcode($output_e);
	}
	
	private function ajax($mode) {

		switch($mode) {
			case 'upd_user':
				$output = '';
				$oname = $this->myPost('oname');
				if($this->userMatch($oname) === FALSE) { echo 'Invalid Target User (If you just renamed the user, close the form and retry after the name update)!'; die(); } 
				$udata = $this->userData($oname);
				if($this->myPost('moderator') && $this->getMod($oname) === FALSE) {
					if($this->hasPermission('MOD', 'skip_msg')) {
						if($udata[4]) {
							$this->writeFile(MODL, "\n".base64_encode($oname), 'a');
							$output .= '- Sucessfully set '.$oname.' as moderator.'."\n";
						} else {
							$output .= '- Cannot set '.$oname.' as moderator: Not using a password.MOD_OFF'."\n";
						}
					}
				}
				if(!$this->myPost('moderator') && $this->getMod($oname) !== FALSE) {
					if($this->hasPermission('UNMOD', 'skip_msg')) {
						if($oname && $this->getMod($par[1]) !== FALSE) {
							$this->writeFile(MODL, str_replace("\n".base64_encode($oname), '', $this->modList), 'w', 'allow_empty');
							$output .= '- Sucessfuly removed '.$oname.' moderator status.'."\n";
						} else {
							$output .= '- '.$oname.': invalid moderator!'."\n";
						}
					}
				}
				if($this->myPost('muted') && $this->getMuted($oname) === FALSE) {
					if($this->hasPermission('MUTE', 'skip_msg')) {
						$xpar = '';
						if($this->myPost('muted_time')) {
                    				if(ctype_digit($this->myPost('muted_time'))) {
                   					$xpar = ' '.(time()+(60*abs(intval($this->myPost('muted_time')))));
                					}
                  			} else { $xpar = ' 0'; }
						$this->writeFile(MUTEDL, preg_replace('/'."\n".base64_encode($oname).' ([0-9]+)/', '', $this->mutedList)."\n".base64_encode($oname).$xpar, 'w');
						$output .= '- Sucessfully muted '.$oname.($this->myPost('muted_time') ? ' for '.abs(intval($this->myPost('muted_time'))).' minute(s)' : '').'.'."\n";
					}
				}
				if(!$this->myPost('muted') && $this->getMuted($oname) !== FALSE) {
					if($this->hasPermission('UNMUTE', 'skip_msg')) {
						$this->writeFile(MUTEDL, preg_replace('/'."\n".base64_encode(trim($oname)).' ([0-9]+)/', '', $this->mutedList), 'w', 'allow_empty');

						$output .= '- Sucessfuly unmuted '.$oname."\n";
					}
				}
				if($this->myPost('banned') && $this->getBanned($oname) === FALSE) {
					if($this->hasPermission('BAN', 'skip_msg')) {
						$xpar = '';
						if($this->myPost('banned_time')) {
                    				if(ctype_digit($this->myPost('banned_time'))) {
                   				$xpar = ' '.(time()+(60*abs(intval($this->myPost('banned_time')))));
                					}
                  			} else { $xpar = ' 0'; }
						$this->writeFile(BANNEDL, preg_replace('/'."\n".base64_encode($oname).' ([0-9]+)/', '', $this->bannedList)."\n".base64_encode($oname).$xpar, 'w');
						$output .= '- Sucessfully banned '.$oname.($this->myPost('banned_time') ? ' for '.abs(intval($this->myPost('banned_time'))).' minute(s)' : '').'.'."\n";
					}
				}
				if(!$this->myPost('banned') && $this->getBanned($oname) !== FALSE) {
					if($this->hasPermission('UNBAN', 'skip_msg')) {
						$this->writeFile(BANNEDL, preg_replace('/'."\n".base64_encode($oname).' ([0-9]+)/', '', $this->bannedList), 'w', 'allow_empty');
						$output .= '- Sucessfuly unbanned '.$oname."\n";
					}
				}

				$changes = 0; $name_err = FALSE;
				if($this->hasPermission('USER_E', 'skip_msg')) {
					if($this->myPost('name') != $oname) {
						if($this->userMatch($this->myPost('name')) !== FALSE) {
							$output .= '- Cannot rename: '.$this->myPost('name').' already exists!';
							$name_err = TRUE;
						} elseif(strlen(trim($this->myPost('name'), ' ')) == 0 || strlen(trim($this->myPost('name'))) > 30 || preg_match("/[\?<>\$\{\}\"\: ]/i", $this->myPost('name'))) {
							$output .= '- Invalid Nickname, too long (max = 30) OR containing invalid characters: ? < > $ { } " : space'."\n";
							$name_err = TRUE;
						} else {
							$changes = 1;
							$output .= '- Name Renamed Successfuly!'."\n";
						}
					}
					
					if($this->myPost('web') != $udata[2]) {
						$udata[2] = $this->myPost('web');
						$changes = 1;
						$output .= '- Web Address Successfully set!'."\n";						
					}

					if($this->myPost('email') != $udata[1]) {
						$udata[1] = $this->myPost('email');
						$changes = 1;
						$output .= '- Email Address Successfully set!'."\n";						
					}

					if($this->myPost('reset_avatar') && $udata[0]) {
						$udata[0] = '';
						$changes = 1;
						$output .= '- Avatar Successfuly Reset!'."\n";
					}

					if($this->myPost('reset_pass') && $udata[5]) {
						$udata[5] = '';
						$changes = 1;
						$output .= '- Password Successfuly Reset!'."\n";
					}

					if($this->myPost('regen_pass')) {
						$npass = $this->randNumb(8);
						$udata[5] = md5(md5($npass));
						$changes = 1;
						$output .= '- Password Successfuly Re-generated: '.$npass."\n";
					}

					if($changes) {
						$nstring = base64_encode($udata[0]).'|'.base64_encode($udata[1]).'|'.base64_encode($udata[2]).'|'.$udata[3].'|'.$udata[4].'|'.$udata[5];
						if($this->myPost('name') == $oname || $name_err)
							$towrite = preg_replace(
								'/('.base64_encode($oname).')\|(.*?)\|/', 
								'\\1|'.base64_encode($nstring).'|', 
								$this->userList
							);
						elseif(trim($this->myPost('name'), ' '))
							$towrite = preg_replace(
								'/('.base64_encode($oname).')\|(.*?)\|/', 
								base64_encode($this->myPost('name')).'|'.base64_encode($nstring).'|', 
								$this->userList
							);

						$this->writeFile(USERL, $towrite, 'w');
					}
					echo trim($output);
				}
			break;
			case 'toggle_status':
				$current = $this->uData[8];

				if($current == 1) {
					$this->writeFile(USERL, $this->updateUser($this->userList, 'update_status', 2), 'w');
					echo 'Your status has been changed to: Do Not Disturb!';
				}
				if($current == 2) {
					$this->writeFile(USERL, $this->updateUser($this->userList, 'update_status', 1), 'w');
					echo 'You status has been changed to: Available!';
				}
			break;
			case 'attach_upl':
				if($_FILES['attach']['tmp_name'] && $this->hasPermission('ATTACH_UPL', 'skip_msg') && ATTACHMENT_UPLOADS) {
					sleep(1);
					$dest_name = preg_replace('/[^A-Za-z0-9 _\.]/', '', $_FILES['attach']['name']);
					$dest = __DIR__ . '/files/attachments/' . base64_encode($this->name) . '_' . dechex(time()). '_' . $dest_name;
					$dest_web = $this->includeDir . 'files/attachments/' . base64_encode($this->name) . '_' . dechex(time()) . '_' . $dest_name;
					$type = strtolower(pathinfo($dest_name, PATHINFO_EXTENSION));
					$file_ok = TRUE;
					$allowed_types = explode(' ', trim(ATTACHMENT_TYPES, ' '));
					$allowed_types_img = array('jpg', 'jpeg', 'gif', 'png');
					if($_FILES['attach']['size'] <= (ATTACHMENT_MAX_FSIZE*1024) && in_array($type, $allowed_types) && !file_exists($dest)) {			
						copy($_FILES['attach']['tmp_name'], $dest);
						if(in_array($type, $allowed_types_img)) {
							echo $this->parseImg($dest_web, 'ATTACH');
						} else {
							echo '[attach_' . base64_encode($this->name).'_'. dechex(time()) . '_' . intval($_FILES['attach']['size']/1014).'_'.$dest_name.']';
						}
					} else {
						if(!file_exists($dest)) {
							echo 'Error: Invalid file (Allowed: Jpg/Gif/Png/Txt/Zip/Pdf up to 1024KB)';
						} else {
							echo 'Error: File already exists!';
						}
					}
				}
			break;
			case 'acc_rec':
				if($this->uData[1] && !file_exists($this->dataDir . 'tmp/rec_'.base64_encode($this->name)) && $this->hasPermission('ACC_REC', 'skip_msg')) {
					if(mail(
						$this->uData[1],
						'Account Recovery',
						"Someone (probably you) has requested an account recovery.\nClick the following link in order to reset your account password:\n\n".$_SERVER['HTTP_REFERER'].'?recover='.$this->uData[5]."&u=".urlencode(base64_encode($this->name))."\n\nIf you did not request this, please ignore it.",
						$this->mailHeaders(
							(trim(ACC_REC_EMAIL) ? trim(ACC_REC_EMAIL) : 'no-reply@'.$_SERVER['SERVER_NAME']),
							TITLE,
							$this->uData[1],
							$this->name
						)
					)) {
						echo 'A message was sent to the account email with recovery instructions.';
						touch($this->dataDir . 'tmp/rec_'.base64_encode($this->name));
					} else {
						echo 'Failed to send E-mail!';
					};
				} elseif(file_exists($this->dataDir . 'tmp/rec_'.base64_encode($this->name))) {
					echo 'A pending recovery already exists for this account.';
				}
			break;
			case 'upd_gsettings':

				if(!$this->hasPermission('GSETTINGS')) { die(); }

				$arr = array();
				$gsettings_par = array('TITLE', 'INCLUDE_DIR', 'REFRESH_DELAY', 'IDLE_START', 'OFFLINE_PING', 'CHAT_DSP_BUFFER', 'CHAT_STORE_BUFFER', 'CHAT_OLDER_MSG_STEP', 'ANTI_SPAM', 'IMAGE_MAX_DSP_DIM',  'IMAGE_AUTO_RESIZE_UNKN', 'VIDEO_WIDTH', 'VIDEO_HEIGHT', 'AVATAR_SIZE', 'DEFAULT_AVATAR', 'DEFAULT_ROOM', 'DEFAULT_THEME', 'INVITE_LINK_CODE', 'ACC_REC_EMAIL', 'ATTACHMENT_TYPES', 'ATTACHMENT_MAX_FSIZE', 'ATTACHMENT_MAX_POST_N');

				foreach($gsettings_par as $key => $value) {
					$arr[$value] = $this->myPost('gs_'.strtolower($value));
				}

				$gsettings_perm = array('GSETTINGS', 'ROOM_C', 'ROOM_E', 'ROOM_D', 'MOD', 'UNMOD', 'USER_E', 'TOPIC_E', 'BAN', 'UNBAN', 'MUTE', 'UNMUTE', 'MSG_HIDE', 'MSG_UNHIDE', 'POST', 'PROFILE_E', 'IGNORE', 'PM_SEND', 'LOGIN', 'ACC_REC', 'READ_MSG', 'ROOM_LIST', 'USER_LIST', 'ATTACH_UPL', 'ATTACH_DOWN');

				$gsettings_perm2 = array('MMOD', 'MOD', 'CUSER', 'USER', 'GUEST');

				foreach($gsettings_perm as $key => $value) {
					$arr['PERM_'.$value] = '';
					foreach($gsettings_perm2 as $key2 => $value2) {
						if($this->myPost(strtolower($value2).'_'.strtolower($value)) == '1') { $arr['PERM_'.$value] .= ' '.$value2; }
					}
					$arr['PERM_'.$value] = trim($arr['PERM_'.$value], ' ');
				}

				$res = $this->updateConf(
					array_merge(
						$arr,
						array(
							'LOAD_EX_MSG' => (($_POST['gs_load_ex_msg'] == '1') ? 'TRUE' : 'FALSE'),
							'LIST_GUESTS' => (($_POST['gs_list_guests'] == '1') ? 'TRUE' : 'FALSE'),
							'GEN_REM_THUMB' => (($_POST['gs_gen_rem_thumb'] == '1') ? 'TRUE' : 'FALSE'),
							'ATTACHMENT_UPLOADS' => (($_POST['gs_attachment_uploads'] == '1') ? 'TRUE' : 'FALSE')		
						)
					)
				);

				if($res === TRUE) {
					echo 'Settings Successfully Updated!';
				} else {
					echo 'Nothing to update!';
				}
			break;
			case 'update_components':
				echo $this->uList().'[$]'.$this->checkTopicChanges().'[$]'.$this->refreshRooms().'[$]'.$this->checkHiddenMsg().'[$]'.$this->updateMsgOnceE().'[$]'.$this->mySession('alert_msg');
				if($this->mySession('alert_msg')) { unset($_SESSION['alert_msg']); }
			break;
			case 'toggle_msg':
				$id = $this->myGet('id');
				$id_hidden = str_replace('|', '*|', $id);
				$action = '';
				if(strpos($this->msgList, $id) !== FALSE) {
					if(!$this->hasPermission('MSG_HIDE', 'skip_msg')) { echo 'NO_ACCESS'; die(); }
					$action = 'hide';
					$this->writeFile(MESSAGES_LOC, str_replace($id, $id_hidden, $this->msgList), 'w');
					echo $this->pTemplate('wcchat.posts.hidden');
				}
				if(strpos($this->msgList, $id_hidden) !== FALSE) {
					if(!$this->hasPermission('MSG_UNHIDE', 'skip_msg')) { echo 'NO_ACCESS'; die(); }
					$action = 'unhide';
					preg_match_all('/'.str_replace(array('*', '|'), array('\*', '\|'), $id_hidden.'|').'(.*)/', $this->msgList, $matches);
					$this->writeFile(MESSAGES_LOC, str_replace($id_hidden, $id, $this->msgList), 'w');
					echo $this->bbcode($matches[1][0]);
				}

				if(strpos($this->hiddenMsgList, $id) === FALSE && $action == 'hide') {
					$this->writeFile(MESSAGES_HIDDEN, ' '.$id, (time()-filemtime(MESSAGES_HIDDEN) > intval((REFRESH_DELAY/1000)*2) ? 'w' : 'a'));
				}
				if(strpos($this->hiddenMsgList, $id) !== FALSE && $action == 'unhide') {
					$this->writeFile(MESSAGES_HIDDEN, str_replace(' '.$id, '', $this->hiddenMsgList), 'w', 'allow_empty');
				}
			break;
			case 'check_hidden_msg':
				echo $this->checkHiddenMsg();
			break;
			case 'refreshtopic':
				echo $this->parseTopicContainer();
			break;
			case 'update_rname':
				if(!$this->hasPermission('ROOM_E')) { die(); }
				$oname = $this->myPost('oname');
				$nname = $this->myPost('nname');
				$perm = $this->myPost('perm');
				$rperm = $this->myPost('rperm');
				$enc = base64_encode($oname);
				$settings = file_get_contents($this->roomDir . 'def_'.$enc.'.txt');
				list($operm, $tmp1, $tmp2, $tmp3, $tmp4) = explode('|', $settings, 5);
				$changes = 0;
				if($operm != $perm || $rperm != $tmp1) {
					file_put_contents($this->roomDir . 'def_'.$enc.'.txt', $perm.'|'.$rperm.'|'.$tmp2.'|'.$tmp3.'|'.$tmp4);
					$changes++;
				}
				if($oname != $nname) {
					rename($this->roomDir . $enc.'.txt', $this->roomDir . base64_encode($nname).'.txt');
					rename($this->roomDir . 'def_'.$enc.'.txt', $this->roomDir . 'def_'.base64_encode($nname).'.txt');
					rename($this->roomDir . 'topic_'.$enc.'.txt', $this->roomDir . 'topic_'.base64_encode($nname).'.txt');
					if(trim($oname) == trim(DEFAULT_ROOM)) {
						file_put_contents(
							__DIR__.'/settings.php',
							str_replace(
								"'DEFAULT_ROOM', '".str_replace("'", "\'", $oname)."'",
								"'DEFAULT_ROOM', '".str_replace("'", "\'", $nname)."'",
								file_get_contents(__DIR__.'/settings.php')
							)
						);
					}
					if($this->mySession('current_room') == $oname) {
						$_SESSION['current_room'] = $nname;
						setcookie('current_room', $nname, time()+(86400*365), '/');
					}
					$changes++;
				}
				if($changes) {
					echo 'Room '.$oname.' Successfully updated!';
					touch(ROOMS_LASTMOD);
				}
			break;
			case 'delete_rname':
				if(!$this->hasPermission('ROOM_D')) { die(); }
				$changes = 0;
				$oname = $this->myGet('oname');
				$enc = base64_encode($oname);
				if(trim($oname) && file_exists($this->roomDir . $enc.'.txt')) {
					if(trim($oname) == DEFAULT_ROOM) {
						echo 'The Default Room cannot be deleted!';
						die();
					}
					unlink($this->roomDir . $enc.'.txt');
					unlink($this->roomDir . 'def_'.$enc.'.txt');
					unlink($this->roomDir . 'topic_'.$enc.'.txt');
					if($this->mySession('current_room') == $oname) {
						$_SESSION['current_room'] = DEFAULT_ROOM;
						setcookie('current_room', DEFAULT_ROOM, time()+(86400*365), '/');
					}
					$changes++;
				}
				if($changes) {
					echo 'Room '.$oname.' Successfully deleted!';
					touch(ROOMS_LASTMOD);
				}
			break;
			case 'croom':
				if(!$this->hasPermission('ROOM_C')) { die(); }
				$room_name = $this->myGet('n');
				if(!file_exists($this->roomDir . base64_encode($room_name).'.txt') && trim($room_name, ' ') && !preg_match("/[\?<>\$\{\}\"\:\|,;]/i", $room_name)) {
					file_put_contents($this->roomDir . base64_encode($room_name).'.txt', time().'|*'.base64_encode($this->name).'|created the room.'."\n");
					file_put_contents($this->roomDir . 'def_'.base64_encode($room_name).'.txt', '0||||');
					file_put_contents($this->roomDir . 'topic_'.base64_encode($room_name).'.txt', '');
					touch(ROOMS_LASTMOD);
					echo $this->rList();
				} else {
					echo 'Room '.$room_name.' already exists OR invalid room name (illegal char.: <b>? < > $ { } " : | , ;</b>)!';
				}
			break;
			case 'changeroom':
				$room_name = $this->myGet('n');
				$_SESSION['current_room'] = $room_name;
				setcookie('current_room', $room_name, time()+(86400*365), '/');

				echo $this->rList();
			break;
			case 'refreshrooms':
				echo $this->refreshRooms();
			break;
			case 'reset_av':
				$efile = '';
				if($this->uData[0]) { list($efile, $_time) = explode('?', $this->uData[0]); }
				if(file_exists($efile)) {
					$nstring = '|'.base64_encode($this->uData[1]).'|'.base64_encode($this->uData[2]).'|'.$this->uData[3].'|'.$this->uData[4].'|'.$this->uData[5];
					$towrite = preg_replace(
						'/('.base64_encode($this->name).')\|(.*?)\|/', 
						'\\1|'.base64_encode($nstring).'|', 
						$this->userList
					);

					$this->writeFile(USERL, $towrite, 'w');
					echo 'Avatar successfully reset!';
					unlink($efile);
				} else {
					echo 'No Avatar to reset!';
				}
			break;
			case 'upl_avatar':
				if(isset($_FILES['avatar']['tmp_name'])) {
					if(!preg_match('#[/jpeg|/png|/gif]#i', $_FILES['avatar']['type'])) {
						echo 'Invalid Image Type (Jpg/Gif/Png only)';
					} else {
						if(!AVATAR_SIZE) { $tn_size = 25; } else { $tn_size = AVATAR_SIZE; }
						$dest = __DIR__.'/files/avatars/'.base64_encode($this->name).'.'.str_replace('image/', '', $_FILES['avatar']['type']);
						$dest_write = base64_encode($this->name).'.'.str_replace('image/', '', $_FILES['avatar']['type']);
						if($this->thumbnail_create($_FILES['avatar']['tmp_name'], $dest, $tn_size)) {
						$nstring = base64_encode($dest_write.'?'.time()).'|'.base64_encode($this->uData[1]).'|'.base64_encode($this->uData[2]).'|'.$this->uData[3].'|'.$this->uData[4].'|'.$this->uData[5];

						$towrite = preg_replace(
							'/('.base64_encode($this->name).')\|(.*?)\|/', 
							'\\1|'.base64_encode($nstring).'|', 
							$this->userList
						);

						$this->writeFile(USERL, $towrite, 'w');
						echo 'Avatar successfully set!';
						}
					}
				}
			break;
			case 'new_start_point':
				$_SESSION['start_point_'.$this->mySession('current_room')] = time();
			break;
			case 'toggle_time':
				if($this->myCookie('hide_time')) {
					setcookie('hide_time', '', time()-3600, '/');
				} else {
					setcookie('hide_time', '1', time()+(86400*365), '/');
				}
			break;
			case 'toggle_edit':
				if($this->myCookie('hide_edit')) {
					setcookie('hide_edit', '', time()-3600, '/');
				} else {
					setcookie('hide_edit', '1', time()+(86400*365), '/');
				}
			break;
			case 'upd_topic':
				if(!$this->hasPermission('TOPIC_E')) { die(); }
				$t = $this->myGET('t');
				$this->writeFile(TOPICL, $t, 'w', 'allow_empty');

				echo $this->parseTopicContainer();
			break;
			case 'check_topic_changes':
				echo $this->checkTopicChanges();
			break;
			case 'get_pass_input':
				echo $this->pTemplate(
					'wcchat.join.password_required',
					array(
						'USER_NAME' => $this->name
					)
				);
			break;
			case 'cmppass':
				$pass = $this->myGet('pass');
				if(md5(md5($pass)) != $this->uData[5])
					echo 0;
				else {
					setcookie('chatpass', md5(md5($pass)), time()+(86400*365), '/');
					$_COOKIE['chatpass'] = md5(md5($pass));
					touch(ROOMS_LASTMOD);
					echo 1;
					if(file_exists($this->dataDir . 'tmp/rec_'.base64_encode($this->name))) {
						unlink($this->dataDir . 'tmp/rec_'.$u);
					}
				}
				
			break;
			case 'smsg':
				if(!$this->hasPermission('POST')) { die(); }
				if(!$this->hasProfileAccess) { echo 'Account Access Denied!'; die(); }
				if(strlen($this->myGet('t')) > 0) {
					if(preg_match('#^(/ignore|/unignore)#i', $this->myGet('t'))) {
						if(!$this->hasPermission('IGNORE')) { die(); }
						$par = explode(' ', trim($this->myGet('t')), 2);
						switch($par[0]) {
							case '/ignore':
								if($this->userMatch($par[1]) !== FALSE) {
									$this->writeEvent('ignore', $par[1]);
									setcookie('ign_'.urlencode(base64_encode($par[1])), '1', time()+(86400*364), '/');
									echo 'Sucessfully ignored '.$par[1];
								} else {
									echo 'User '.$par1.' does not exist.';
								}
							break;
							case '/unignore':
								if($this->userMatch($par[1]) !== FALSE && $this->myCookie('ign_'.base64_encode($par[1]))) {
									$this->writeEvent('unignore', $par[1]);
									setcookie('ign_'.urlencode(base64_encode($par[1])), '', time()-3600, '/');
									echo 'Sucessfully unignored '.$par[1];
								} else {
									echo 'User '.$par1.' does not exist / Not being ignored.';
								}
							break;
						}
					} else {
						$muted_msg = $banned_msg = '';
						$muted = $this->getMuted($this->name);
						if($muted !== FALSE && $this->name) {
							$muted_msg = 'You have been set as mute'.($muted ? ' ('.$this->idlet($muted, 1).' remaining)' : '').', you cannot talk for the time being!';
						}
						if($this->isBanned !== FALSE) {
							$banned_msg = 'You are banned'.(intval($this->isBanned) ? ' ('.intval(($this->isBanned-time())).' seconds remaining)' : '').'!';
						}
						if(!$muted_msg && !$banned_msg) {

							if(!$this->hasRoomPermission($this->mySession('current_room'), 'W')) {
								echo 'You don\'t have permission to post messages here!'; die();
							}
							if((time()-$this->mySession('lastpost')) < ANTI_SPAM) {
								echo 'Anti Spam: Please allow '.ANTI_SPAM.'s between posted messages!';
								die();
							}
							$name_prefix = '';
							$text = $this->myGet('t');
							if(preg_match('#^(/me )#i', $text)) {
								$name_prefix = '*';
								$text = str_replace('/me ', '', $text);
							}
							if(preg_match('#^(/pm )#i', $text)) {
								if(!$this->hasPermission('PM_SEND')) { die(); }
								list($target, $ntext) = explode(' ', str_replace('/pm ', '', $text), 2);
								if(strlen(trim($ntext)) > 0 && strlen(trim($target)) > 0) {
									$target = trim($target, ' ');
									
									if($this->userMatch($target) === FALSE) {
										echo 'User '.$target.' does not exist!'; die();
									} else {
										$user_status = $this->userData($target);
										if((time() - $this->getPing($target)) < OFFLINE_PING && $user_status[8] == 2) {
											echo 'User '.$target.' does not want to be disturbed at the moment!'; die();
										}
									}
								} else {
                   						echo 'Invalid Private Message Syntax ("/pm <user> <message>")'; die();             								}
								$name_prefix = base64_encode($target).'-';
								$text = trim($ntext, ' ');
							}

							if(
								!preg_match('/((http|ftp)+(s)?:\/\/[^<>\s]+)(.jpg|.jpeg|.png|.gif)/i', $text) && 
								!preg_match('/\[IMG\](.*?)\[\/IMG\]/i', $text)
							) {
								$text = trim($text);
							} else {
								$text = preg_replace_callback(
									'/(?<!=\"|\[IMG\])((http|ftp)+(s)?:\/\/[^<>\s]+(\.jpg|\.jpeg|\.png|\.gif))/i',
									array($this, 'parseImg'),
									trim($text)
								);
								$text = preg_replace_callback(
									'/\[IMG\](.*?)\[\/IMG\]/i',
									array($this, 'parseImg'),
									trim($text)
								);
							}

							$source = $this->msgList;
							if(substr_count($source, "\n") >= CHAT_STORE_BUFFER) {
								list($v1, $v2) = explode("\n", $source, 2);
								$source = $v2;
							}

							$towrite = time().'|'.$name_prefix.base64_encode($this->name).'|'.strip_tags($text)."\n";
							$this->writeFile(MESSAGES_LOC, $source.$towrite, 'w');
							touch(ROOMS_LASTMOD);
							list($output, $index) = $this->parseMsg(array(trim($towrite)), 0, 'SEND');
							$_SESSION['lastpost'] = time();
							echo $this->bbcode($output);
						} else {
							echo ($banned_msg ? $banned_msg : $muted_msg);
						}
					}
				}
			break;
			case 'updsett':
				if(!$this->hasProfileAccess) { echo 'NO_ACCESS'; die(); }
				if(!$this->hasPermission('PROFILE_E', 'skip_msg')) { echo 'NO_ACCESS'; die(); }
				$user_data = $this->userData();
				$email = $this->myPost('email');
				$web = $this->myPost('web');
				$timezone = $this->myPost('timezone');
				$hformat = $this->myPost('hformat');

				if((!filter_var($email, FILTER_VALIDATE_EMAIL) && trim($email)) || (!filter_var($web, FILTER_VALIDATE_URL) && trim($web))) {
					echo 'INVALID';
					die();
				}

				if($this->myPost('resetp') == '1') { $pass = ''; } else {
					$passe = md5(md5($this->myPost('pass')));
					$pass = ($this->myPost('pass') ? $passe : $this->uData[5]);
					// if($this->myPost('pass')) { setcookie('chatpass', $passe, time()+(86400*365), '/'); }
				}

				$nstring = base64_encode($this->uData[0]).'|'.base64_encode($email).'|'.base64_encode($web).'|'.$timezone.'|'.$hformat.'|'.$pass;

				$towrite = preg_replace(
						'/('.base64_encode($this->name).')\|(.*?)\|/', 
						'\\1|'.base64_encode($nstring).'|', 
						$this->userList
					);

				$this->writeFile(USERL, $towrite, 'w');
				if($timezone != $this->uData[3] || $hformat != $this->uData[4]) { echo 'RELOAD_MSG'; }
				if($pass != '') { echo ' RESETP_CHECKBOX'; }
				if($this->myPost('pass') && $this->myPost('resetp') != '1') { echo ' RELOAD_PASS_FORM'; }

			break;
			case 'smsge':
				if($this->myGet('t')) {
					if((time()-filemtime(EVENTL)) > 60) { $mode = 'w'; } else { $mode = 'a'; }
					switch($this->myGet('t')) {
						case 'join':
							$towrite = time().'|'.base64_encode($this->name).'|<b>'.$this->name.'</b> has joined chat.'."\n";
						break;
						case 'topic_update':
							$towrite = time().'|'.base64_encode($this->name).'|<b>'.$this->name.'</b> updated the topic ('.$this->mySession('current_room').').'."\n";
						break;
					}
					$this->writeFile(EVENTL, $towrite, $mode);
					$output = $this->parseMsgE(array(trim($towrite)), 0, 'SEND');
					if($output) { echo $this->bbcode($output); }
				}
			break;
			case 'updu':
				echo $this->uList();
			break;
			case 'updmsg_e':
				echo $this->updateMsgOnceE();
			break;
			case 'updmsg':
				if($this->isBanned !== FALSE) { echo 'You are banned!'; die(); }
				if(!$this->hasPermission('READ_MSG', 'skip_msg')) { echo 'Can\'t display messages.'; die(); }
				$output = '';
				$lastmod = filemtime(MESSAGES_LOC);

				$lastread = $this->handleLastRead('read');

				if($this->myGet('all') == 'ALL' && $this->mySession('cname') && LIST_GUESTS === TRUE) {
					$online = $this->userList;
					if($this->userMatch($this->name) === FALSE) {
						$this->writeFile(USERL, "\n".base64_encode($this->name).'|'.$this->userDataString.'|0|'.time().'|0','a');
					} else {
						$this->updateUser($online, 'lurker_visit');
					}
				}

				if($this->myGet('all') != 'ALL' && !$lastread) { die(); }

				$previous = $skip_new = 0;
				$new = '';
				$lines = array();
				$index = 0;				

				if($this->mySession('reset_msg')) { $_GET['all'] = 'ALL'; }
				if($lastmod > $lastread || $this->myGet('all') == 'ALL' || $this->myGet('n')) { 
					if(!isset($_SESSION['start_point_'.$this->mySession('current_room')]) && LOAD_EX_MSG === FALSE) { $_SESSION['start_point_'.$this->mySession('current_room')] = time(); }
					$this->handleLastRead('store');

					if(strlen($this->msgList) > 0) {
						$lines = explode("\n", trim($this->msgList));
						list($output, $index) = $this->parseMsg($lines, $lastread, 'RETRIEVE', $this->myGet('n'));
					}
				}
				$older = '';
				if(count($lines) > $index && $this->myGet('all') == 'ALL' && LOAD_EX_MSG === TRUE) {
					$older = $this->pTemplate('wcchat.posts.older');
				}
				if($this->mySession('reset_msg')) {
					$output = 'RESET'.$output;
					unset($_SESSION['reset_msg']);
				}
				if(trim($output)) { echo $older.($this->myGet('n') ? str_replace('wc_doscroll()', '', $this->bbcode($output)) : $this->bbcode($output)); }
				if($this->myGet('n') || $this->myGet('all') == 'ALL') { sleep(1); }
			break;
		}
		
	}

	private function pTemplate($model, $data = NULL, $cond = NULL, $no_cond_content = NULL)
	{
		$out = '';
		if (!isset($cond) || (isset($cond) && $cond == TRUE))
		{
			// Import template model
			$out = $this->templates[$model];

			// Replace tokens
			if (isset($data)) {
				if (is_array($data)) {
					foreach ($data as $key => $value) {
						$out = str_replace("{" . $key . "}", stripslashes($data[$key]), $out);
					}
				}
			}
			$out = trim($out, "\n\r");

			return str_replace(
				array(
					'{CALLER}',
					'{INCLUDE_DIR}',
					'{INCLUDE_DIR_THEME}'
				),
				array(
					$this->ajaxCaller,
					$this->includeDir,
					INCLUDE_DIR_THEME
				),
				$out
			);
		} else {
			if($no_cond_content == NULL) {
				return '';
			} else {
				return $no_cond_content;
			}
		}
	}

	private function smiley($field)
	{
		$out = '';
		$s1 =
			array(
				'1' => ':)',
				'3' => ':o',
				'2' => ':(',
				'4' => ':|',
				'5' => ':frust:',
				'6' => ':D',
				'7' => ':p',
				'8' => '-_-',
				'10' => ':E',
				'11' => ':mad:',
				'12' => '^_^',
				'13' => ':cry:',
				'14' => ':inoc:',
				'15' => ':z',
				'16' => ':love:',
				'17' => '@_@',
				'18' => ':sweat:',
				'19' => ':ann:',
				'20' => ':susp:',
				'9' => '>_<'
			);

		foreach($s1 as $key => $value)
			$out .=
				$this->ptemplate('wcchat.toolbar.smiley.item',
					array(
						'title' => $value,
						'field' => $field,
						'str_pat' => $value,
						'str_rep' => 'sm'.$key.'.gif'
					)
				);

		return($out);

	}

	private function rList() {

		$rooms = $create_room = '';

		foreach(glob($this->roomDir . '*.txt') as $file) {
			$room_name = base64_decode(str_replace(array($this->roomDir, '.txt'), '', $file));
			if(strpos($file, 'def_') === FALSE && strpos($file, 'topic_') === FALSE && strpos($file, 'hidden_') === FALSE) {

				$edit_form = $edit_icon = '';
				if($this->hasPermission('ROOM_E', 'skip_msg')) {
					list($perm,$t1,$t2,$t2,$t4) = explode('|', file_get_contents($this->roomDir . 'def_'.base64_encode($room_name).'.txt'));
					$enc = base64_encode($file);
					$edit_form = 
						$this->pTemplate(
							'wcchat.rooms.edit_form',
							array(
								'ID' => $enc,
								'ROOM_NAME' => $room_name,
								'SEL1' => ($perm == '1' ? ' SELECTED' : ''),
								'SEL2' => ($perm == '2' ? ' SELECTED' : ''),
								'SEL3' => ($perm == '3' ? ' SELECTED' : ''),
								'SEL21' => ($t1 == '1' ? ' SELECTED' : ''),
								'SEL22' => ($t1 == '2' ? ' SELECTED' : ''),
								'SEL23' => ($t1 == '3' ? ' SELECTED' : ''),
								'SEL24' => ($t1 == '4' ? ' SELECTED' : ''),
								'DELETE_BT' => $this->pTemplate('wcchat.rooms.edit_form.delete_bt', array('ID' => $enc), $room_name != DEFAULT_ROOM)						
							)
						);
					$edit_icon = $this->pTemplate(
						'wcchat.rooms.edit_form.edit_icon', 
						array(
							'ID' => base64_encode($file),
							'OFF' => ($this->myCookie('hide_edit') == 1 ? '_off' : '')
						)
					);
				}

				if($this->hasRoomPermission($room_name, 'R')) {
					if($room_name == $this->mySession('current_room')) {
						$rooms .=
							$this->pTemplate(
								'wcchat.rooms.current_room',
								array(
									'TITLE' => $room_name,
									'EDIT_BT' => $edit_icon,
									'FORM' => $edit_form,
									'NEW_MSG' => $this->pTemplate('wcchat.rooms.new_msg.off')
								)
							);
					} else {
						$lastread = $this->handleLastRead('read', $room_name);
						$lastmod = filemtime($this->roomDir . base64_encode($room_name).'.txt');
						$rooms .= 
							$this->pTemplate(
								'wcchat.rooms.room',
								array(
									'TITLE' => $room_name,
									'EDIT_BT' => $edit_icon,
									'FORM' => $edit_form,
									'NEW_MSG' => $this->pTemplate('wcchat.rooms.new_msg.'.(($lastread < $lastmod) ? 'on' : 'off'))
								)
							);
					}
				}
			}
		}

		$create_room = '';
		if($this->hasPermission('ROOM_C', 'skip_msg')) {
			$create_room =
				$this->pTemplate('wcchat.rooms.create', array('OFF' => $this->myCookie('hide_edit') == 1 ? '_off' : ''));
		}

		if(!$this->hasPermission('ROOM_LIST', 'skip_msg')) { $rooms = 'Can\'t display rooms.'; }

		return $this->pTemplate('wcchat.rooms.inner', array('ROOMS' => $rooms, 'CREATE' => $create_room));
	}

	private function getThemes() {
		$options = '';
		foreach(glob(INCLUDE_DIR.'themes/*') as $file) {
			$title = str_replace(INCLUDE_DIR.'themes/', '', $file);
			if($title != '.' AND $title != '..') {
				$options .= $this->pTemplate(
					'wcchat.themes.option',
					array(
						'TITLE' => $title,
						'VALUE' => $title,
						'SELECTED' => (($title == THEME) ? ' SELECTED' : '')
					)
				);
			}
		}
		return $this->pTemplate('wcchat.themes', array('OPTIONS' => $options));
	}

	private function printIndex() {

		$onload = $contents = '';

		if($this->myCookie('cname') && $this->isCertifiedUser)
			$_SESSION['cname'] = $this->myCookie('cname');

		if($this->mySession('cname')) {
			$JOIN = $this->pTemplate(
				'wcchat.join.inner',
				array(
					'PASSWORD_REQUIRED' => 
						$this->pTemplate(
							'wcchat.join.password_required', 
							array(
								'USER_NAME' => $this->name
							), 
							$this->uData[5] && !$this->isCertifiedUser
						),
					'MODE' => (($this->uData[5] && !$this->isCertifiedUser) ? $this->pTemplate('wcchat.join.inner.mode.login', '') : $this->pTemplate('wcchat.join.inner.mode.join', '')),
					'CUSER_LINK' => $this->pTemplate(
						'wcchat.join.cuser_link', 
						array(
							'RETURN_URL' => urlencode($_SERVER['REQUEST_URI'])
						)
					),
					'USER_NAME' => $this->name,
					'RECOVER' => $this->pTemplate(
						'wcchat.join.recover',
						'',
						$this->uData[1] && $this->uData[5] && !$this->isCertifiedUser && $this->hasPermission('ACC_REC', 'skip_msg')
					)
				)
			);
		} else {
			$err = '';
			if($this->mySession('login_err')) {
				$err = $this->mySession('login_err');
				unset($_SESSION['login_err']);
			}

			$JOIN = $this->pTemplate(
				'wcchat.login_screen',
				array(
					'USER_NAME_COOKIE' => $this->myCookie('cname'),
					'CALLER_NO_PAR' => trim($this->ajaxCaller, '?'),
					'ERR' => $this->pError($err)
				)
			);

		}

		$BBCODE = ((!$this->mySession('cname') && $this->myCookie('cname') && !$this->uData[5]) ? 
					'<i>Hint: Set-up a password in settings to skip the login screen on your next visit.</i>' :
					$this->pTemplate(
						'wcchat.toolbar.bbcode',
						array(
							'SMILIES' => $this->smiley('wc_text_input_field'),
							'FIELD' => 'wc_text_input_field',
							'ATTACHMENT_UPLOADS' => $this->pTemplate(
								'wcchat.toolbar.bbcode.attachment_uploads',
								array(
									'ATTACHMENT_MAX_POST_N' => ATTACHMENT_MAX_POST_N
								),
								ATTACHMENT_UPLOADS && $this->hasPermission('ATTACH_UPL', 'skip_msg')
							)
						),
						$this->mySession('cname')
					)
				);

		$gsettings_par = array('TITLE', 'INCLUDE_DIR', 'REFRESH_DELAY', 'IDLE_START', 'OFFLINE_PING', 'CHAT_DSP_BUFFER', 'CHAT_STORE_BUFFER', 'CHAT_OLDER_MSG_STEP', 'ANTI_SPAM', 'IMAGE_MAX_DSP_DIM',  'IMAGE_AUTO_RESIZE_UNKN', 'VIDEO_WIDTH', 'VIDEO_HEIGHT', 'AVATAR_SIZE', 'DEFAULT_AVATAR', 'DEFAULT_ROOM', 'DEFAULT_THEME', 'INVITE_LINK_CODE', 'ACC_REC_EMAIL', 'ATTACHMENT_TYPES', 'ATTACHMENT_MAX_FSIZE', 'ATTACHMENT_MAX_POST_N');

		$gsettings_par_v = array();

		foreach($gsettings_par as $key => $value) {
			$gsettings_par_v['GS_'.$value] = constant($value);
		}
		$gsettings_par_v['GS_LOAD_EX_MSG'] = (LOAD_EX_MSG === TRUE ? ' CHECKED' : '');
		$gsettings_par_v['GS_LIST_GUESTS'] = (LIST_GUESTS === TRUE ? ' CHECKED' : '');
		$gsettings_par_v['GS_GEN_REM_THUMB'] = (GEN_REM_THUMB === TRUE ? ' CHECKED' : '');
		$gsettings_par_v['GS_ATTACHMENT_UPLOADS'] = (ATTACHMENT_UPLOADS === TRUE ? ' CHECKED' : '');

		$gsettings_perm = array('GSETTINGS', 'ROOM_C', 'ROOM_E', 'ROOM_D', 'MOD', 'UNMOD', 'USER_E', 'TOPIC_E', 'BAN', 'UNBAN', 'MUTE', 'UNMUTE', 'MSG_HIDE', 'MSG_UNHIDE', 'POST', 'PROFILE_E', 'IGNORE', 'PM_SEND', 'LOGIN', 'ACC_REC', 'READ_MSG', 'ROOM_LIST', 'USER_LIST', 'ATTACH_UPL', 'ATTACH_DOWN');

		$gsettings_perm2 = array('MMOD', 'MOD', 'CUSER', 'USER', 'GUEST');

		foreach($gsettings_perm as $key => $value) {
			$perm_data = constant('PERM_'.$value);
			$perm_fields = explode(' ', $perm_data);
			foreach($gsettings_perm2 as $key2 => $value2) {
				$gsettings_par_v['GS_'.$value2.'_'.$value] = (in_array($value2, $perm_fields) ? ' CHECKED' : '');
			}
		}

		$contents = $this->pTemplate(
			'wcchat',
			array(
				'TITLE' => TITLE,
				'TOPIC' => $this->pTemplate('wcchat.topic', array('TOPIC' => $this->parseTopicContainer())),
				'STATIC_MSG' => (!$this->mySession('cname') ? $this->pTemplate('wcchat.static_msg') : ''),
				'POSTS' => $this->pTemplate('wcchat.posts'),
				'GSETTINGS' => ($this->hasPermission('GSETTINGS', 'skip_msg') ? 
					$this->pTemplate(
						'wcchat.global_settings',
						array_merge(
							array(
								'URL' => ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://').$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'],
								'ACC_REC_EM_DEFAULT' => 'no-reply@' . $_SERVER['SERVER_NAME']
							),
							$gsettings_par_v
						)
					) : 
					''
				),
				'TOOLBAR' => $this->pTemplate(
					'wcchat.toolbar',
					array(
						'BBCODE' => ($BBCODE ? $BBCODE : 'Choose a name to use in chat.'),
						'USER_NAME' => $this->name ? str_replace("'", "\'", $this->name) : 'Guest',
						'TIME' => time(),
						'COMMANDS' => $this->pTemplate(
							'wcchat.toolbar.commands',
							array(
								'GSETTINGS' => $this->pTemplate(
									'wcchat.toolbar.commands.gsettings', 
									'', 
									$this->hasPermission('GSETTINGS', 'skip_msg')
								),
								'EDIT' => $this->pTemplate(
									'wcchat.toolbar.commands.edit', 
									'', 
									$this->hasPermission('ROOM_C', 'skip_msg') || $this->hasPermission('ROOM_E', 'skip_msg') || $this->hasPermission('ROOM_D', 'skip_msg') || $this->hasPermission('MOD', 'skip_msg') || $this->hasPermission('UNMOD', 'skip_msg') || $this->hasPermission('USER_E', 'skip_msg') || $this->hasPermission('TOPIC_E', 'skip_msg') || $this->hasPermission('BAN', 'skip_msg') || $this->hasPermission('UNBAN', 'skip_msg') || $this->hasPermission('MUTE', 'skip_msg') || $this->hasPermission('UNMUTE', 'skip_msg') || $this->hasPermission('MSG_HIDE', 'skip_msg') || $this->hasPermission('MSG_UNHIDE', 'skip_msg')  
								)
							)
						),
						'ICON_STATE' => ($this->hasProfileAccess ? '' : 'closed'),
						'JOINED_STATUS' => $this->pTemplate('wcchat.toolbar.joined_status', array('MODE' => 'on'))
					)
				),
				'TEXT_INPUT' => $this->pTemplate('wcchat.text_input', array('CHAT_DSP_BUFFER' => CHAT_DSP_BUFFER)),
				'SETTINGS' => $this->pTemplate(
					'wcchat.settings',
					array(
						'AV_RESET' => ($this->uData[0] ? '' : 'closed'),
						'TIMEZONE_OPTIONS' => str_replace(
							'value="'.$this->userTimezone.'"', 
							'value="'.$this->userTimezone.'" SELECTED', 
							$this->pTemplate('wcchat.settings.timezone_options')
						),
						'HFORMAT_SEL0' => ($this->hFormat == '0' ? ' SELECTED' : ''),
						'HFORMAT_SEL1' => ($this->hFormat == '1' ? ' SELECTED' : ''),
						'USER_LINK' => $this->userLink,
						'USER_EMAIL' => $this->userEmail,
						'RESETP_ELEM_CLOSED' => ($this->uData[5] ? '' : 'closed')
					)
				),
				'JOIN' => $this->pTemplate('wcchat.join', array('JOIN' => $JOIN)),
				'ROOM_LIST' => $this->pTemplate('wcchat.rooms', array('RLIST' => $this->rList())),
				'USER_LIST' => $this->pTemplate('wcchat.users', array('ULIST' => $this->uList('VISIT'))),
				'THEMES' => $this->getThemes()
			)
		);

		$onload = ($this->mySession('cname') ? $this->pTemplate('wcchat.toolbar.onload', array('CHAT_DSP_BUFFER' => CHAT_DSP_BUFFER, 'EDIT_BT_STATUS' => intval($this->myCookie('hide_edit')))) : $this->pTemplate('wcchat.toolbar.onload_once', array('CHAT_DSP_BUFFER' => CHAT_DSP_BUFFER)));

		$tag = '';
		if($this->isBanned !== FALSE) {
			$tag = ((intval($this->isBanned) == 0) ? ' (Permanently)' : ' ('.$this->idlet($this->isBanned, 1).' remaining)');
		}

		echo $this->pTemplate(
			($this->embedded !== TRUE ? 'index' : 'index_embedded'),
			array(
				'TITLE' => TITLE,
				'CONTENTS' => ($this->isBanned !== FALSE ? $this->pTemplate('index.critical_error', array('TITLE' => TITLE, 'ERROR' => 'You are banned!'.$tag)) : ($this->stopMsg ? $this->pTemplate('index.critical_error', array('TITLE' => TITLE, 'ERROR' => $this->stopMsg)) : $contents)),
				'ONLOAD' => (($this->isBanned !== FALSE || $this->stopMsg) ? '' : $onload),
				'REFRESH_DELAY' => REFRESH_DELAY,
				'STYLE_LASTMOD' => filemtime(__DIR__.'/themes/'.THEME.'/style.css'),
				'SCRIPT_LASTMOD' => filemtime(__DIR__.'/script.js'),
				'DEFAULT_THEME' => DEFAULT_THEME
			)
		);

	}

	private function myPost($index) {

		if (isset($_POST[$index])) {
			if ($this->hasData($_POST[$index])) {
				return $_POST[$index];
			}
		}
		return '';
	}

	private function myGet($index) {

		if (isset($_GET[$index])) {
			if ($this->hasData($_GET[$index])) {
				return $_GET[$index];
			}
		}
		return '';
	}

	private function myCookie($index) {

		if (isset($_COOKIE[$index])) {
			if ($this->hasData($_COOKIE[$index])) {
				return $_COOKIE[$index];
			}
		}
		return '';
	}

	private function mySession($index) {

		if (isset($_SESSION[$index])) {
			if ($this->hasData($_SESSION[$index])) {
				return $_SESSION[$index];
			}
		}
		return '';
	}

	private function myServer($index) {

		if (isset($_SERVER[$index])) {
			if ($this->hasData($_SERVER[$index])) {
				return $_SERVER[$index];
			}
		}
		return '';
	}

	private function hasData($var) {

		if (strlen($var) > 0) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	private function updateConf($array) {

		$conf = file_get_contents(__DIR__ . '/settings.php');
		$conf_bak = $conf;

		foreach($array as $key => $value) {
			$constant_value = constant($key);
			if($constant_value === TRUE) { $constant_value = 'TRUE'; }
			if($constant_value === FALSE) { $constant_value = 'FALSE'; }
			if(ctype_digit($value) || $value == 'TRUE' || $value == 'FALSE') {
				$conf = str_replace(
					"define('{$key}', {$constant_value})", 
					"define('{$key}', {$value})", 
					$conf
				);
			} else {
				$conf = str_replace(
					"define('{$key}', '{$constant_value}')", 
					"define('{$key}', '{$value}')", 
					$conf
				);
			}
		}
		$handle = fopen(__DIR__ . '/settings.php', 'w');
		fwrite($handle, $conf);
		fclose($handle);

		if(file_get_contents(__DIR__ . '/settings.php') != $conf_bak) {
			return TRUE;
		} else
			return FALSE;	
	}

	private function mailHeaders($from,$fname,$to,$toname) {
		$headers = "MIME-Version: 1.0\n";
		$headers.= "From: ".$fname." <".$from.">\n";
		$headers .= "To: ".$toname."  <".$to.">\n";
		$headers .= "Reply-To: " . $from . "\r\n";
		$headers .= "X-Mailer: PHP/" . phpversion() . "\n";
		$headers .= "Return-Path: <" . $from . ">\n";
		$headers .= "Errors-To: <" . $from . ">\n"; 
		$headers .= "Content-Type: text/plain; charset=iso-8859-1\n";

		return($headers);
	}

	private function randNumb($n) {
		$output = '';
      	$salt = '0123456789AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz';
      	srand((double)microtime()*1000000);
      	$i = 0;
      	while ($i < $n) {
			$num = rand() % 33;
			$tmp = substr($salt, $num, 1);
			$output = $output . $tmp;
			$i++;
		}
		return $output;
	}
}

?>