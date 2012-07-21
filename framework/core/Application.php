<?php
namespace beaba\core;
/**
 * This file is distributed under the MIT Open Source
 * License. See README.MD for details.
 * @author Ioan CHIRIAC
 */
class Application {    
    protected $services;
    protected $instances = array();
    /**
     * Initialize the application
     */
    public function __construct() {
        $this->getService('logger')->debug('Application starts');
        $this->getService('errors')->attach(
            $this->getService('logger')
        );
    }
    /**
     * Gets a service instance
     * @param string $name 
     * @return IService
     */
    public function getService( $name ) {
        if ( !isset( $this->instances[ $name ] ) ) {
            if (!$this->services) {
                $this->services = get_include('config/services.php');
            }
            if ( !isset( $this->services[ $name ]) ) {
                throw new \Exception(
                    'Undefined service : ' . $name
                );
            }
            $this->instances[ $name ] = new $this->services[ $name ]( $this );
        }
        return $this->instances[ $name ];
    }   
    /**
     * Gets the asset manager
     * @return IAssets
     */
    public function getAssets() {
        return $this->getService('assets');
    }
    /**
     * Gets the response handler
     * @return IResponse
     */
    public function getResponse() {
        return $this->getService('response');
    }
    /**
     * Gets the view manager
     * @return IView
     */
    public function getView() {
        return $this->getService('view');
    }
    /**
     * Execute the specified action controller 
     * @param string $controller
     * @param string $action
     * @param array $params 
     * @return string
     */
    public function execute( $controller, $action, $params ) {
        $instance = new $controller( $this );
        return $instance->execute( $action, $params );
    }    
    /**
     * Dispatching the specified request
     * @param string $url
     * @param array $params 
     */
    public function dispatch( $url, $params ) {        
        try {
            $route = $this->getService('router')->getRoute( $url );
            if ( $route === false ) {
                throw new Exception('No route found', 404);
            }
        } catch( \Exception $ex ) {
            if ( $ex instanceof Exception && !$ex->isHttpError() ) {
                $this->getService('response')->setCode(
                    $ex->getCode(), $ex->getHttpMessage()
                );                
            } else {
                $this->execute(
                    'beaba\\controllers\\errors', 'show', 
                    array(
                        'request' => $url,
                        'params' => $params,
                        'error' => $ex
                    )
                );                
            }
        }
    }
}
/**
 * The service interface
 */
interface IService {
    /**
     * Gets the current application
     * @return Application
     */
    function getApplication();
}
/**
 * The assets manager structure
 */
interface IAssets extends IService {
    /**
     * Check if the specified package is defined
     * @param string $package 
     * @return boolean
     */    
    public function hasConfig( $package );
    /**
     * Gets the specified package configuration
     * @param string $package 
     * @return array
     * @throws Exception
     */
    public function getConfig( $package );
    /**
     * Attach a package to the current app
     * @param string $package 
     * @return void
     */
    public function attach( $package );
    /**
     * Remove the package usage
     * @param string $package 
     * @return void
     */
    public function detach( $package );
    /**
     * Retrieves the list of css includes
     * @return array
     */
    public function getCss();
    /**
     * Gets a list of JS links
     * @return array
     */
    public function getJs();    
}
/**
 * Services interfaces
 */
interface IResponse extends IService {
    /**
     * Sets the response code
     */
    function setCode( $code, $message );
    /**
     * Write a new line with the specified message
     */
    function writeLine( $message );
}
/**
 * The logger interface
 */
interface ILogger extends IService {
    /**
     * Logs debug infos
     */
    const DEBUG     = 1; // 0001
    /**
     * Logs info 
     */
    const INFO      = 2; // 0010
    /**
     * Logs warnings
     */
    const WARNING   = 4; // 0100
    /**
     * Logs errors
     */
    const ERROR     = 8; // 1000    
    /**
     * Gets the current logger level
     * @return int
     */
    function getLevel();
    /**
     * Sets the log level
     */
    function setLevel( $level );
    /**
     * Send a debug message
     */
    function debug($message);
    /**
     * Sends an info message
     */
    function info($message);
    /**
     * Send a warning message
     */
    function warning($message);
    /**
     * Send an error message
     */
    function error($message);
}
/**
 * The error handler
 */
interface IErrorHandler {
    /**
     * Attach an logger and starts watching errors
     */
    function attach( ILogger $logger );
    /**
     * Detach the error handler
     */
    function detach();
    /**
     * Catch the specified exception
     */
    function catchException( \Exception $ex );
}

/**
 * Inner service class (automatically loaded)
 */
class Service implements IService {    
    /**
     * @var Application
     */
    protected $app;
    /**
     * Initialize the service
     * @param Application $app 
     */
    public function __construct( Application $app ) {
        $this->app = $app;
    }
    /**
     * Gets the current application
     * @return Application 
     */
    public function getApplication() {
        return $this->app;
    }
}
