<?php
/*************************
	General settings
*************************/

// If these are changed then all stored documents become inaccessible!
$hashSalt      = 'AodoKSThWzxjjODE';
$fileSalt      = 'hLl3bc6id86TZ7YZ';
$serverPowSalt = '5Yuxp01PSprE0DF8';

// Expected amount of work is:
//     Client: $nSolutions * pow(16, $leadingZeros)
//     Server: $nSolutions
$leadingZeros = 2;
$nSolutions   = 200;

$documentUri = '/api/documents';

/*************************
	Helper variables (plus a function)
*************************/
$isHttpMethod = function ($method) use ($_SERVER) {
	return $_SERVER['REQUEST_METHOD'] == $method;
};

$_PUT = $isHttpMethod('PUT') ?
	json_decode(file_get_contents('php://input'), true) :
	array();

$_DELETE = $isHttpMethod('DELETE') ?
	json_decode(file_get_contents('php://input'), true) :
	array();


/*************************
	Helper functions, maybe these could be grouped into classes
*************************/
$json = function($result) {
	header('Content-Type: application/json');
	die(json_encode($result));
};

$hash = function ($str) {
	return hash('sha256', $str, false);
};


$getHash = function ($mode, $a, $b) use ($hash, $hashSalt) {
	return $hash($mode . $hash($mode . $a . $hashSalt) . $b);
};

$getFile = function ($getId, $file) use ($documentUri) {
	$document = json_decode(file_get_contents($file), true);
	
	// A migration step, will be removed in future
	if (isset($document['proof_of_work'])) {
		$document['proofOfWork'] = $document['proof_of_work'];
		unset($document['proof_of_work']);
	}
	
	$response = array(
		'getId' => $getId,
		'self'  => "$documentUri/$getId",
		'found' => true,
		'proofOfWork' => $document['proofOfWork']
	);
	
	if (isset($document['contents'])) {
		$response['contents'] = $document['contents'];
	}
	
	return $response;
};

$deleteFile = function ($getId, $file) use ($_DELETE) {
	if (!isset($_DELETE['putId'])) {
		return array(
			'error' => 'No putId in DELETE!'
		);
	}
	
	$document = json_decode(file_get_contents($file), true);
	
	if (
		isset($document['putId']) &&
		$document['putId'] != $_DELETE['putId']
	) {
		return array(
			'getId' => $getId,
			'error' => 'putIds do not match!'
		);
	}
	
	unlink($file);
	
	$folder = dirname($file);
	if (!(new \FilesystemIterator($folder))->valid()) {
		// Empty the delete folder
		rmdir($folder);
	}
	
	return array(
		'getId'   => $getId,
		'deleted' => true
	);
};

$validatePow = function ($response, $clientPowSalt) use (
	$nSolutions, $leadingZeros, $hash, $json
) {
	//TODO: this should be cached to APC or something...
	if (sizeof($response) != $nSolutions) {
		$json(array(
			'success' => false,
			'error'   => sprintf(
				'Wrong number of POW elements: %d instead of %d',
				sizeof($response), $nSolutions
			)
		));
	}
	
	foreach ($response as $r) {
		$h = $hash($hash($r . $clientPowSalt) . $r);
		
		if (strlen($h) - strlen(ltrim($h, '0')) < $leadingZeros) {
			$json(array(
				'success' => false,
				'error'   => sprintf("Invalid hash from '%s': %s!", $r, $h)
			));
		}
	}
};

$storeDocument = function (
	$getId, $putId, $file, $proofOfWork, $powResponse
) use ($documentUri, $_PUT) {
	$proofOfWork['response'] = $powResponse;
	
	$document = array(
		'getId'       => $getId,
		'putId'       => $putId,
		'proofOfWork' => $proofOfWork
	);
	
	if (isset($_PUT['contents'])) {
		$document['contents'] = $_PUT['contents'];
	}
	
	$folder = dirname($file);
	if (!is_dir($folder)) {
		mkdir($folder);
	}
	
	file_put_contents($file, json_encode($document));
	return "$documentUri/$getId";
};


/*************************
	Main "controller"
*************************/
if (preg_match("_^$documentUri/([^/]+)_", $_SERVER['DOCUMENT_URI'], $route)) {
	$getId  = $route[1];
	$file   = substr($getHash('filename', $getId, $fileSalt), 32);
	$folder = str_replace("\\", '/', __DIR__) . '/data/' . substr($file, -3);
	$file   = $folder . '/' . substr($file, 0, -3) . '.json';
	
	//TODO: Make sure $folder is writeable!!!
	
	$clientPowSalt = $getHash('POW', $getId, $serverPowSalt);
	$proofOfWork = array(
		'hash'          => 'SHA-256',
		'formula'       => 'H(H(%x || %salt) || %x)',
		'salt'          => $clientPowSalt,
		'leadingZeros'  => $leadingZeros,
		'nSolutions'    => $nSolutions
	);
	
	$fileExists = is_dir($folder) && file_exists($file);
	
	if ($fileExists) {
		if ($isHttpMethod('GET')) {
			$json($getFile($getId, $file));
		}
		
		if ($isHttpMethod('DELETE')) {
			$json($deleteFile($getId, $file));
		}
	}
	
	if (!isset($_PUT['proofOfWork']) || !isset($_PUT['proofOfWork']['response'])) {
		// To modify the file POW has to be included, send the challenge
		$json(array(
			'getId'        => $getId,
			'proofOfWork' => $proofOfWork
		));
	}
	
	if (!isset($_PUT['putId'])) {
		$json(array(
			'success' => false,
			'error'   => 'putId missing!'
		));
	}
	
	$putId = $_PUT['putId'];
	
	if ($fileExists) {
		$document = json_decode(file_get_contents($file), true);
		
		if (isset($document['putId']) && $document['putId'] != $putId) {
			$json(array(
				'success' => false,
				'error'   => 'putIds do not match!'
			));
		}
	}
	
	$powResponse = $_PUT['proofOfWork']['response'];
	$validatePow($powResponse, $clientPowSalt);
	$uri = $storeDocument($getId, $putId, $file, $proofOfWork, $powResponse);
	
	$json(array(
		'success' => true,
		'self'    => $uri
	));
}

// Catch-all option
$json(array(
	'links' => array(
		'document' => array(
			'template'    => '/api/documents/{id}',
			'description' => 'Store and retrieve a document'
		)
	)
));
