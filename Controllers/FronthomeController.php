<?php
namespace Apps\Controllers;

class HomeController
{
	protected $container;

	public function __construct($container)
	{
	      $this->container = $container; 
	}
	
	public function __get($property)
	{
	    if($this->container->{$property}) 
	    {
	        return $this->container->{$property};
	    }
	}	

	public function one($request, $response)
	{			
	    $stmt = $this->db->prepare("SELECT * FROM users");
	    $stmt->execute();
	    $dowork = $stmt->fetchAll();	    
	    echo $pass = $this->security->encryptPassword("asdfdsfsdf"); 
	    die;	    
	    //return $this->response->withJson($dowork);
	}

	public function index($request,$response)
	{
		//print_r($this->db); die;
		//echo "<pre>";
		//print_r($request); die;
		//return "HomeController11";		
		$resArr['code']  = 1;
		$resArr['error'] = true;
		return $response->withJson($resArr); 
	}

	public function privateContest($request,$response){

		$resArr['code']  = 1;
		$resArr['error'] = true;
		return $response->withJson($resArr);
	}

}


?>
