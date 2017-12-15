<?php 
defined('CMS') or die("This file cannot run this way!");
class messages{
	
	var $messages = array();
	var $errorMessages = array();
	var $hasErrors = false;
	var $hasMessages = false;
	
	function addMessage($msg, $session=false){
		if(!$msg) return false;
		$this->messages[] = $msg;
		if($session){
			$_SESSION["odyssey_messages"] = $this->messages;
		}
		$this->hasMessages = true;
		return true;
	}
	
	
	function addError($msg, $session=false){
		if(!$msg) return false;
		$this->errorMessages[] = $msg;
		if($session){
			$_SESSION["odyssey_errors"] = $this->errorMessages;
		}
		$this->hasErrors = true;
		return true;
	}
	
		
	function printNotifyMessages(){
		if(!$this->hasMessages || !count($this->messages)) return false;
		echo "<div class='notify odyssey_system_msg'><a onclick='$(\"div.odyssey_system_msg\").remove();' class='closeSystemMessage'></a><div class='notifyMessage'>";
		$this->messages = array_unique($this->messages);
		foreach($this->messages as $msg){
				echo stripslashes($msg);
				if(!preg_match('/^<li>/', $msg)) echo "<br>";
		}
		echo '</div></div>';
		$this->messages=array();
		return true;
	}

	
	function printErrorMessages(){
		if(!$this->hasErrors || !count($this->errorMessages)) return false;
		$this->errorMessages = array_unique($this->errorMessages);
		echo "<div class='error odyssey_system_msg'><a onclick='$(\"div.odyssey_system_msg\").remove();' class='closeSystemMessage'></a><div class='errorMessage'>";
			foreach($this->errorMessages as $error){
				echo stripslashes($error);
				if(!preg_match('/^<li>/', $error)) echo "<br>";
			}
		echo '</div></div>';
		$this->errorMessages=array();
		return true;
	}


	function printSuccessMessage($msg){
		if(!$msg) return false;
		echo '<div align="center">';
		echo '<img src="images/ok.png" alt="οκ" style="margin-top: 100px;">';
		echo '<br><br>';
		echo '<b>'.stripslashes($msg).'</b></div>';
		return true;
	}
	
	
	
	function importSessionMessages(){
		if(!count($_SESSION["odyssey_errors"]) && !count($_SESSION["odyssey_messages"])) return false; // no messages of any type to display
		if(is_array($_SESSION["odyssey_errors"])) {
			foreach($_SESSION["odyssey_errors"] as $error){
				$this->addError($error);
			}
			unset($_SESSION["odyssey_errors"]);
		}
		if(is_array($_SESSION["odyssey_messages"])) {
			foreach($_SESSION["odyssey_messages"] as $message){
				$this->addMessage($message);
			}
			unset($_SESSION["odyssey_messages"]);
		}
		return true;
	}
	
	function printSystemMessages($timeout = 0, $margin=50){
		if(!count($this->messages) && !$this->hasErrors) return false;
		$this->errorMessages = array_unique($this->errorMessages);
		$this->messages = array_unique($this->messages);
		$margin = (!empty($margin)) ? $margin : 0;
		$lines = count($this->messages) + count($this->errorMessages);
		echo("<div id='msg_spacer' style='margin-top:".$margin."px'></div>");
		if($timeout){		
		$timeout = $lines * $timeout;
		echo("
		<script type='text/javascript'>
		<!--
		jQuery().ready(function(){
			setTimeout(function(){
			  $('#system_message').animate({
			    opacity: 0,
			    height: '1px'
			  }, 200, function() {
			    $(this).remove();
			  });
			}, ".$timeout.");
			
			setTimeout(function(){
			$('#msg_spacer').animate({
			    height: '1px'
			  }, 200, function() {
			    $(this).remove();
			  });
			}, ".$timeout.");  
			  
		});
		//-->
		</script>
		     ");
		}
		echo "<div id='system_message'>";
		$this->printErrorMessages();
		$this->printNotifyMessages();
		echo "</div>";
	}
	
	function clear($type="all", $session=false){
   		if($session) $this->importSessionMessages();
		if($type=="messages" || $type=="all") $this->messages = array();
		if($type=="errors" || $type=="all") $this->errorMessages = array();
		return;
	}
	

} // end class

?>