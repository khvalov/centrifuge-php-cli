<?php
namespace CentrifugeClient;

Class CentrifugeClient {
	const EVENT_CONNECTED='connected', EVENT_DISCONNECTED='disconnected';

	public $token;
	public $serverUrl;

	private $loop;
	private $app;
	private $reactConnector;
	private $connector;

	private $_subsciptions;
	private $_events;
	private $_incomingMessages; 
	private $_expectedReplies; //Storing expected replies from server (at least for auth result evaluation)

	private $conn;


	public function __construct($serverUrl){
		$this->serverUrl=$serverUrl;

		$this->loop = \React\EventLoop\Factory::create();
	    $reactConnector = new \React\Socket\Connector($this->loop, [
	        'timeout' => 10
	    ]);

	    $this->connector = new \Ratchet\Client\Connector($this->loop, $reactConnector);
	}

	public function setToken($token){
		$this->token=$token;
	}

	public function connect(){
		$connector=$this->connector;
		$connector('ws://12eb91cc-6a64-4f7b-9fac-29e0e6442af7.flock.local/connection/websocket')
			->then(function(\Ratchet\Client\WebSocket $conn) {

			//Trying to authiticate using Token
			$this->authenticate($conn);

			//Looping over subscription and attaching event
	        $_subsciptions=$this->getSubscriptions();

	        $i=10;
	        foreach($_subsciptions as $sub){
	        	$this->registerExpectedReply($i); 
	        	$conn->send('{"method":"subscribe","params":{"channel":"'.$sub['channel'].'"},"id":'.$i.'}');
	        	$i++;
	        }

	        //Launching conected events
	        $this->runEvents(self::EVENT_CONNECTED);


	        //Default behavior on connection close
	        $conn->on('close', function($code = null, $reason = null) {
	        	$this->runEvents(self::EVENT_DISCONNECTED,[$code=>$reason]);
	            $this->loop->stop();
	        });

	        //Handling messages
	        $conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn) {
	            $this->messageHandler(strval($msg));
	        });


	    }, function(\Exception $e){
	        echo "Could not connect: {$e->getMessage()}\n";
	        $this->loop->stop();
	    });

		$this->loop->run();
	}


	public function messageHandler($msg){
		if(!empty($msg)){
			//Messages are comes by frames and may contain multiple messages insde. Edge it with [] to consider as multidimension json
			$msg="[".trim($msg)."]";
			//Replacing newlines to comma to make string JSON-like
			$msg=preg_replace("/[\n\r]/",",",$msg);
			$msg=json_decode($msg,true);
			if(is_array($msg)){
				foreach($msg as $item){
					$isControl=$this->assertControlReply($item);
					if(!$isControl){

						$this->_incomingMessages[]=$item;

						//Looping over the channel we have to subscribe and looking for lambda function to run
						$_subsciptions=$this->getSubscriptions();
				        foreach($_subsciptions as $sub){
				        	if($sub['channel'] == $item['result']['channel']){
				        		$function=$sub['callback'];

				        		if(is_callable($function)){
					        		$function($item['result']['data']);
					        		break;
				        		}
				        	}
				        }
					}
				}
			}
		}
	}

	//Checking whether its control message or user
	private function assertControlReply($message){
		if(!empty($this->_expectedReplies)){
			foreach($this->_expectedReplies as $key=>$expectedReply){
				if($message['id'] == $expectedReply['id']){
					if(array_key_exists('result', $message)) {
						unset($this->_expectedReplies[$key]);
					}
					if(array_key_exists('error', $message)) {
						echo "Unexpected reply: ID:".$message['id']." ".$message["error"]["code"]." ".$message["error"]["message"].PHP_EOL;
						$this->_errors[]=$message;
					}

					return true;
				} 
			}
		}
		return false;
	}

	public function getErrors(){
		return $this->_errors;
	}

	public function subscribe($channel,$callback){
		$this->_subsciptions[]=['channel'=>$channel,'callback'=>$callback];
	}

	public function getSubscriptions(){
		return $this->_subsciptions;
	}

	public function unsubscribe($channel){

	}

	public function refresh(){

	}

	private function runEvents($event,$object=null){
		foreach($this->_events[$event] as $function){
			$function($object);
		}
	}

	public function on($event,$callback){
		$this->_events[$event][]=$callback;
	}


	private function createAuthMessage(){
		if(!$this->token){
			throw new \Exception("Auth token is not defined. Please set auth token");
		}

		$tokenString=json_encode([
			    "id"=>1, //Hardcoded ID
			    "method"=> "connect",
			    "params"=> [
			        "token"=> $this->token
			    ]
			]);

		return $tokenString;
	}

	private function authenticate($conn){
		
		$this->registerExpectedReply(1); //Auth packet have allways ID:1

		$conn->send($this->createAuthMessage());
	}

	public function registerExpectedReply($id){
		$this->_expectedReplies[]=['id'=>$id];
	}

}
