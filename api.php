<?php
$json = function($result) {
	header('Content-Type: application/json');
	die(json_encode($result));
};

$hash = function ($str) { return hash('sha256', $str, false); };

$hashSalt = 'Ac9DzqfS31XRxdlV';
$fileSalt = 'hLl3bc6id86TZ7YZ';
$powSalt  = '5Yuxp01PSprE0DF8';

$getHash = function ($a, $b, $mode) use ($hash, $hashSalt) {
	return $hash($mode . $hash($mode . $a . $hashSalt) . $b);
};

$_PUT = ($_SERVER['REQUEST_METHOD'] == 'PUT') ?
	json_decode(file_get_contents('php://input'), true) :
	array();

$_DELETE = ($_SERVER['REQUEST_METHOD'] == 'DELETE') ?
	json_decode(file_get_contents('php://input'), true) :
	array();

if (preg_match('_^/api/([^/]+)_', $_SERVER['DOCUMENT_URI'], $route)) {
	$get_id  = $route[1];
	$file   = substr($hash($fileSalt . $hash($get_id . $fileSalt)), 32);
	$folder = str_replace("\\", '/', __DIR__) . '/data/' . substr($file, -3);
	$file   = $folder . '/' . substr($file, 0, -3) . '.json';
	
	$salt         = $getHash('POW', $get_id, $powSalt);
	$leadingZeros = 16 * 4;
	
	$proofOfWork = array(
		'hash'          => 'SHA-256',
		'formula'       => 'H(H(%x || %salt) || %x)',
		'salt'          => $salt,
		'leading_zeros' => $leadingZeros
	);
	
	$exists = is_dir($folder) && file_exists($file);
	
	if ($exists) {
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			$contents = json_decode(file_get_contents($file), true);
			$response = array(
				'get_id' => $get_id,
				'found'  => true,
				'proof_of_work' => $contents['proof_of_work']
			);
			
			if (isset($contents['contents'])) {
				$response['contents'] = $contents['contents'];
			}
			
			$json($response);
		}
		
		if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
			if (!isset($_DELETE['put_id'])) {
				$json(array(
					'error' => 'No put_id in DELETE!'
				));
			}
			
			$contents = json_decode(file_get_contents($file), true);
			
			if (
				isset($contents['put_id']) &&
				$contents['put_id'] != $_DELETE['put_id']
			) {
				$json(array(
					'get_id' => $get_id,
					'error'  => 'put_ids do not match!'
				));
			}
			
			unlink($file);
			if (!(new \FilesystemIterator($folder))->valid()) {
				rmdir($folder);
			}
			
			$json(array(
				'get_id'  => $get_id,
				'deleted' => true
			));
		}
	}
	
	if (
		!isset($_PUT['proof_of_work']) &&
		!isset($_PUT['proof_of_work']['response'])
	) {
		// Send the challenge
		$json(array(
			'get_id'        => $get_id,
			'proof_of_work' => $proofOfWork
		));
	}
	
	// Validate the response
	$response = $_PUT['proof_of_work']['response'];
	$result    = $hash($hash($response . $salt) . $response);
	$len       = preg_match('/^0+/', $result, $l) ? strlen($l[0])*16 : 0;
	$success   = $len >= $leadingZeros;
	
	$result = array(
		'result'        => $result,
		'leading_zeros' => array(
			'expected' => $leadingZeros,
			'result'   => $len
		),
		'success' => $success
	);
	
	if (!$success) {
		$result['error'] = 'Insufficient number of leading zeros in result!';
		$json($result);
	}
	
	if (!isset($_PUT['put_id'])) {
		$result['error']   = 'put_id missing!';
		$result['success'] = false;
		$json($result);
	}
	
	if (!is_dir($folder)) {
		mkdir($folder);
	}
	
	$proofOfWork['response'] = $response;
	$contents = array(
		'get_id'        => $get_id,
		'put_id'        => $_PUT['put_id'],
		'proof_of_work' => $proofOfWork
	);
	
	if (isset($_PUT['contents'])) {
		$contents['contents'] = $_PUT['contents'];
	}
	
	file_put_contents($file, json_encode($contents));
	$json($result);
}
