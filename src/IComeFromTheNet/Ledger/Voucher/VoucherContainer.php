<?php
namespace IComeFromTheNet\Ledger\Voucher;

use DateTime;
use Pimple; 
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

use IComeFromTheNet\Ledger\Voucher\DB\VoucherGroupBuilder;
use IComeFromTheNet\Ledger\Voucher\DB\VoucherGroupGateway;
use IComeFromTheNet\Ledger\GatewayProxyCollection;
use IComeFromTheNet\Ledger\Voucher\DB\VoucherTypeGateway;
use IComeFromTheNet\Ledger\Voucher\DB\VoucherTypeBuilder;
use IComeFromTheNet\Ledger\Voucher\DB\VoucherInstanceBuilder;
use IComeFromTheNet\Ledger\Voucher\DB\VoucherInstanceGateway;
use IComeFromTheNet\Ledger\Voucher\DB\VoucherGenRuleBuilder;
use IComeFromTheNet\Ledger\Voucher\DB\VoucherGenRuleGateway;

use IComeFromTheNet\Ledger\Voucher\Operations\GroupCreate;
use IComeFromTheNet\Ledger\Voucher\Operations\GroupRemove;
use IComeFromTheNet\Ledger\Voucher\Operations\GroupRevise;

use IComeFromTheNet\Ledger\Voucher\Operations\VoucherCreate;

use IComeFromTheNet\Ledger\Voucher\Operations\RuleCreate;
use IComeFromTheNet\Ledger\Voucher\Operations\RuleRevise;

use IComeFromTheNet\Ledger\Voucher\Operations\TypeCreate;
use IComeFromTheNet\Ledger\Voucher\Operations\TypeRevise;
use IComeFromTheNet\Ledger\Voucher\Operations\TypeExpire;

/**
 * Voucher Service Container
 * 
 * @author Lewis Dyer <getintouch@icomefromthenet.com>
 * @since 1.0.0
 */ 
class VoucherContainer extends Pimple
{
    
    /**
     *  These constants are the internal names of the table
     *  and are the key columns in the map
     */
     
    const DB_TABLE_VOUCHER_TYPE     = 'ledger_voucher_type' ;
    const DB_TABLE_VOUCHER_GROUP    = 'ledger_voucher_group' ;
    const DB_TABLE_VOUCHER_INSTANCE = 'ledger_voucher_instance' ;
    const DB_TABLE_VOUCHER_RULE     = 'ledger_voucher_gen_rule';
    
    
    /**
     * Return the modules database metadata.
     * 
     * This not record FK or other indexes.
     * 
     * @return Doctrine\DBAL\Schema\Schema
     * @param Doctrine\DBAL\Schema\Schema $sc The schema to add new tables too
     */ 
    protected function createModuleDBMeta(Schema $sc)
    {
        
        $sGroupTableName    = self::DB_TABLE_VOUCHER_GROUP;
        $sInstanceTableName = self::DB_TABLE_VOUCHER_INSTANCE;
        $sRuleTableName     = self::DB_TABLE_VOUCHER_RULE;
        $sTypeTableName     = self::DB_TABLE_VOUCHER_TYPE;
        
        # Voucher Groups
        $table = $sc->createTable($sGroupTableName);
        $table->addColumn('voucher_group_id','integer',array("unsigned" => true,'autoincrement' => true));
        $table->addColumn('voucher_group_name','string',array("length" => 100));
        $table->addColumn('voucher_group_slug','string',array("length" => 100));
        $table->addColumn('is_disabled','boolean',array("default"=>false));
        $table->addColumn('sort_order','integer',array("unsigned" => true));
        $table->addColumn('date_created','datetime',array());
        
        $table->setPrimaryKey(array('voucher_group_id'));
        $table->addUniqueIndex(array('voucher_group_slug'),'gl_voucher_group_uiq1');
            
        
        # Voucher Rules
        $table = $sc->createTable($sRuleTableName);
        $table->addColumn('voucher_rule_name','string',array('length'=> 25));
        $table->addColumn('voucher_rule_slug','string',array("length" => 25));
        $table->addColumn('voucher_gen_rule_id','integer',array('unsigned'=> true,'autoincrement' => true));
        $table->addColumn('voucher_padding_char','string',array('legnth'=>'1'));
        $table->addColumn('voucher_prefix','string',array('length'=> 50));
        $table->addColumn('voucher_suffix','string',array('length'=>50));
        $table->addColumn('voucher_length','smallint',array('unsigned'=> true,'length'=>3));
        $table->addColumn('date_created','datetime',array());
        $table->addColumn('voucher_sequence_no','integer',array('unsigned'=> true));
        $table->addColumn('voucher_sequence_strategy','string',array('length'=> 20));
        
        
        $table->setPrimaryKey(array('voucher_gen_rule_id'));
        
        
        # Voucher Type Table
        $table = $sc->createTable($sTypeTableName);
         $table->addColumn('voucher_type_id','integer',array("unsigned" => true,'autoincrement' => true));
        $table->addColumn("voucher_enabled_from", "datetime",array());
        $table->addColumn("voucher_enabled_to", "datetime",array());
        $table->addColumn('voucher_name','string',array('length'=>100));
        $table->addColumn('voucher_name_slug','string',array('length'=>100));
        $table->addColumn('voucher_description','string',array('length'=>500));
        $table->addColumn('voucher_group_id','integer',array('unsigned'=> true));
        $table->addColumn('voucher_gen_rule_id','integer',array('unsigned'=> true));
        
        
        $table->setPrimaryKey(array('voucher_type_id'));
        $table->addForeignKeyConstraint('ledger_voucher_group',array('voucher_group_id'),array('voucher_group_id'),array(),'gl_voucher_type_fk1');
        $table->addForeignKeyConstraint('ledger_voucher_gen_rule',array('voucher_gen_rule_id'),array('voucher_gen_rule_id'),array(),'gl_voucher_type_fk2s');
        $table->addUniqueIndex(array('voucher_name','voucher_enabled_from'),'gl_voucher_type_uiq1');
        
        
        # Vouchers Table (Instance Table)
        $table = $sc->createTable($sInstanceTableName);
        $table->addColumn('voucher_instance_id','integer',array("unsigned" => true,'autoincrement' => true));
        $table->addColumn('voucher_type_id','integer',array("unsigned" => true));
        $table->addColumn('voucher_code','string',array("length"=> 255));
        $table->addColumn('date_created','datetime',array());
        
        $table->setPrimaryKey(array('voucher_instance_id'));
        $table->addForeignKeyConstraint('ledger_voucher_type',array('voucher_type_id'),array('voucher_type_id'),array(),'gl_voucher_instance_fk1');
        $table->addUniqueIndex(array('voucher_code'),'gl_voucher_instance_uiq1');
        
        return $sc;
        
    }
    
    
    /**
     * DI Container constrcutor
     * 
     * @param Doctrine\DBAL\Connection  $db The Database connection
     * @param Symfony\Component\EventDispatcher\EventDispatcherInterface $oEvent    The event dispatcher
     * @param Psr\Log\LoggerInterface   $oLogger    The App Logger
     * @param GatewayProxyCollection    $col    A collection to hold the gateways
     */ 
    public function __construct(Connection $db, EventDispatcherInterface $oEvent, LoggerInterface $oLogger, GatewayProxyCollection $col) 
    {
        $this['database']    = $db;
        $this['event']       = $oEvent;
        $this['gatewayProxyCollection'] = $col;
        $this['logger']      = $oLogger;
    }
    
    
    /**
     * Return the assigned database adapter
     * 
     * @return Doctrine\DBAL\Connection
     */ 
    public function getDatabaseAdapter()
    {
        return $this['database'];
    }
    
    /**
     * Return the assigned event dispatcher
     * 
     * @return Symfony\Component\EventDispatcher\EventDispatcherInterface
     */ 
    public function getEventDispatcher()
    {
        return $this['event'];
    }
    
    /**
     * Return the assigned event dispatcher
     * 
     * @return Psr\Log\LoggerInterface
     */ 
    public function getAppLogger()
    {
        return $this['logger'];
    }
    
    /**
     * 
     * 
     */ 
    public function getVoucherGroupGateway()
    {
        return $this['gatewayVoucherGroup'];
    }
    
    /**
     * 
     * 
     */ 
    public function getVoucherTypeGateway()
    {
        return $this['gatewayVoucherType'];
    }
    
    
    /**
     * 
     * 
     */ 
    public function getVoucherInstanceGateway()
    {
        return $this['gatewayVoucherInstance'];
    }
    
    /**
     * 
     * 
     */ 
    public function getVoucherRuleGateway()
    {
        return $this['gatewayVoucherRule'];
    }
    
    /**
     *  Return the proxy gateway collection
     * 
     * @access public
     * @return IComeFromTheNet\Ledger\GatewayProxyCollection
     */ 
    public function getGatewayProxyCollection()
    {
        return $this['gatewayProxyCollection'];
    }
    
    
    /**
     * Return array of operations used in VoucherGroup CRUD
     * 
     * @return array(
     *  'create' => IComeFromTheNet\Ledger\Voucher\Operations\GroupCreate,
     *  'delete' => IComeFromTheNet\Ledger\Voucher\Operations\GroupRemove,
     *  'update' => IComeFromTheNet\Ledger\Voucher\Operations\GroupRevise,
     * )
     */ 
    public function getVoucherGroupOperations()
    {
        return $this['voucherGroupOperations'];
    }
    
    /**
     * Return array of operations used in VoucherInstance CRUD
     * 
     * @return array(
     *  'create' => IComeFromTheNet\Ledger\Voucher\Operations\VoucherCreate
     * )
     */
    public function getVoucherInstanceOperations()
    {
        return $this['voucherInstanceOperations'];
    }
    
    /**
     * Return array of operations used in VoucherGenRule CRUD
     * 
     * @return array(
     *  'create' = IComeFromTheNet\Ledger\Voucher\Operations\RuleCreate
     *  'update' => IComeFromTheNet\Ledger\Voucher\Operations\RuleRevise
     * )
     */
    public function getVoucherRuleOperations()
    {
        return $this['voucherRuleOperations'];
    }
    
    /**
     * Return array of operations used in VoucherType CRUD
     * 
     * @return array(
     *  'create' => IComeFromTheNet\Ledger\Voucher\Operations\TypeCreate
     *  'update' => IComeFromTheNet\Ledger\Voucher\Operations\TypeRevise
     *  'delete' => IComeFromTheNet\Ledger\Voucher\Operations\TypeExpire
     * )
     */
    public function getVoucherTypeOperations()
    {
        return $this['voucherTypeOperations'];
    }
    
    /**
     * Fetch the assigned now date
     * 
     * return DateTime
     */ 
    public function getNow()
    {
        return  $this['now'];
    }
    
    
    //--------------------------------------------------------------------------
    # Service Bootstrap
    
    
    /**
     *  Build this services dependecies, only call once
     *  
     * @return void
     */ 
    public function boot(DateTime $now)
    {
        
        $this['now'] = $now;
        
        # build the table meta data using the map  
        $this['dbMeta'] = $this->createModuleDBMeta($this->getGatewayProxyCollection()->getSchema());
        
        
        # instance the gateways
        
        $this['gatewayVoucherGroup'] = $this->share(function($c) {
            
            $sAlias = 'a';
            
            # connection
            $oConnection = $this->getDatabaseAdapter();
            
            # metadata
            $oTable = $c['dbMeta']->getTable(self::DB_TABLE_VOUCHER_GROUP);
            
            # builder
            $oBuilder = new VoucherGroupBuilder();
            $oBuilder->setTableQueryAlias($sAlias);
            
            
            # event
            $oEvent  = $this->getEventDispatcher();
            
            $oGateway = new VoucherGroupGateway(self::DB_TABLE_VOUCHER_GROUP,$oConnection,$oEvent,$oTable,null,$oBuilder);
            $oGateway->setTableQueryAlias($sAlias);
            $oGateway->setGatewayCollection($c->getGatewayProxyCollection());
            
            return  $oGateway;
            
        });
        
        $this['gatewayVoucherType'] = $this->share(function($c) {
             $sAlias = 'd';
            
            # connection
            $oConnection = $this->getDatabaseAdapter();
            
            # metadata
            $oTable = $c['dbMeta']->getTable(self::DB_TABLE_VOUCHER_TYPE);
            
            # builder
            $oBuilder = new VoucherTypeBuilder();
            $oBuilder->setTableQueryAlias($sAlias);
            
            
            # event
            $oEvent  = $this->getEventDispatcher();
            
            $oGateway = new VoucherTypeGateway(self::DB_TABLE_VOUCHER_TYPE,$oConnection,$oEvent,$oTable,null,$oBuilder);
            $oGateway->setTableQueryAlias($sAlias);
             $oGateway->setGatewayCollection($c->getGatewayProxyCollection());
            
            return  $oGateway;
            
        });
        
        $this['gatewayVoucherInstance'] = $this->share(function($c) {
            
            
            $sAlias = 'c';
            
            # connection
            $oConnection = $this->getDatabaseAdapter();
            
            # metadata
            $oTable = $c['dbMeta']->getTable(self::DB_TABLE_VOUCHER_INSTANCE);
            
            # builder
            $oBuilder = new VoucherInstanceBuilder();
            $oBuilder->setTableQueryAlias($sAlias);
            
            
            # event
            $oEvent  = $this->getEventDispatcher();
            
            $oGateway = new VoucherInstanceGateway(self::DB_TABLE_VOUCHER_INSTANCE,$oConnection,$oEvent,$oTable,null,$oBuilder);
            $oGateway->setTableQueryAlias($sAlias);
            $oGateway->setGatewayCollection($c->getGatewayProxyCollection());
            
            return  $oGateway;
            
        });
        
        
        $this['gatewayVoucherRule'] = $this->share(function($c) {
            
             $sAlias = 'b';
            
            # connection
            $oConnection = $this->getDatabaseAdapter();
            
            # metadata
            $oTable = $c['dbMeta']->getTable(self::DB_TABLE_VOUCHER_RULE);
            
            # builder
            $oBuilder = new VoucherGenRuleBuilder();
            $oBuilder->setTableQueryAlias($sAlias);
            
            
            # event
            $oEvent  = $this->getEventDispatcher();
            
            $oGateway = new VoucherGenRuleGateway(self::DB_TABLE_VOUCHER_RULE,$oConnection,$oEvent,$oTable,null,$oBuilder);
            $oGateway->setTableQueryAlias($sAlias);
            $oGateway->setGatewayCollection($c->getGatewayProxyCollection());
            
            return  $oGateway;
            
        });
        
        # Add gateways to proxy collection
        $col = $this->getGatewayProxyCollection();
        $col->addGateway(self::DB_TABLE_VOUCHER_RULE    , $this->raw('gatewayVoucherRule'));
        $col->addGateway(self::DB_TABLE_VOUCHER_INSTANCE, $this->raw('gatewayVoucherInstance'));
        $col->addGateway(self::DB_TABLE_VOUCHER_TYPE    , $this->raw('gatewayVoucherType'));
        $col->addGateway(self::DB_TABLE_VOUCHER_GROUP   , $this->raw('gatewayVoucherGroup'));
     
        
        # Voucher Operations Setup These are not shared
        
        $this['voucherGroupOperations'] = function($c) use($now) {
            
            $oGroupGateway = $this->getVoucherGroupGateway();
            
            return array(
                'create' => new GroupCreate($oGroupGateway,$now),
                'delete' => new GroupRemove($oGroupGateway,$now),
                'update' => new GroupRevise($oGroupGateway,$now),
            ); 
            
        };
        
        
        $this['voucherInstanceOperations'] = function($c) use($now) {
          
            $oInstanceGateway = $c->getVoucherInstanceGateway();
          
            return array(
              'create' => new VoucherCreate($oInstanceGateway,$now)
              
            );  
            
        };
        
        
        $this['voucherRuleOperations'] =  function($c) use($now) {
            
            $oInstanceGateway = $c->getVoucherRuleGateway();
            
            return array(
               'create' => new RuleCreate($oInstanceGateway, $now)
              ,'update' => new RuleRevise($oInstanceGateway, $now)
            );  
            
        };
        
        $this['voucherTypeOperations'] = function($c) use($now) {
            
            $oInstanceGateway = $c->getVoucherTypeGateway();
            
            return array(
               'create'  => new TypeCreate($oInstanceGateway, $now)
              ,'update'  => new TypeRevise($oInstanceGateway, $now)
              ,'delete'  => new TypeExpire($oInstanceGateway, $now)
              
            ); 
            
        };
        
    }
    
}
/* End of File */