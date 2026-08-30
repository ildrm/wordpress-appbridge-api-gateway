<?php
namespace AppBridge\ApiGateway\Integrations;
defined( 'ABSPATH' ) || exit;
final class DetectedIntegration implements IntegrationInterface {
	public function __construct(private string $id,private string $label,private $detector,private array $features=array(),private ?string $version_constant=null){}
	public function key():string{return $this->id;} public function name():string{return $this->label;}
	public function available():bool{return (bool)call_user_func($this->detector);} public function capabilities():array{return $this->features;}
	public function register_routes():void{}
	public function health():array{return array('ok'=>$this->available(),'version'=>$this->version_constant&&defined($this->version_constant)?constant($this->version_constant):null);}
}
