# centrifuge-php-cli
Centrifuge server client written on PHP7 

```
require('./vendor/autoload.php');

$token='Your JWT Token. Feel free to use any or \Firebase\JWT libbrary to create it';

$centrifugeClient=new CentrifugeClient('ws://your-server-running-centrifuge/connection/websocket');
$centrifugeClient->setToken($token); //Put your token 

$centrifugeClient->on(CentrifugeClient::EVENT_CONNECTED,function(){
	echo "Connected"; //configuring Events if any
});

$centrifugeClient->on(CentrifugeClient::EVENT_DISCONNECTED,function($event){
	var_dump($event); //configuring Events if any
});

$centrifugeClient->subscribe('3efe3c40-c0fa-11e8-b3f4-1c51435d0814',function($message){
	echo "Beginning of process...";
	var_dump($message);
	echo "End of the process...";

});

$centrifugeClient->connect(); //running main loop
```


#Installing: 

```
composer install khvalov/centrifuge-php-cli 
```

#Events: 

Currently Library support only 2 events: 
- EVENT_CONNECTED
- EVENT_DISCONNECTED

#Known limits

Yeah, it's PHP and it's designed to die (c). Constant loop using PHP is defenetly bad idea, but nevertheless here some known limits: 
- It's not supports (at least now) unsubscribe function by design
- Running php 7.1+ version as utilizing lambda functions a lot 
- Not supporting reconnection yet (have some ideas how to do it) 
- Not tested so much yet, not recomended for production related products/services
- Support only websocket WS protocol (and most probably WSS, but not tested) 

Any contributions are welcome
