<?php 
namespace IComeFromTheNet\Ledger\Voucher\Operations;

use DateTime;
use DBALGateway\Exception as DBALGatewayException;
use IComeFromTheNet\Ledger\Voucher\VoucherException;
use IComeFromTheNet\Ledger\Voucher\DB\VoucherGenRule;
use IComeFromTheNet\Ledger\Voucher\DB\VoucherGenRuleGateway;

/**
 * Operation will save a new voucher rule, not be used to update existing
 * 
 * @author Lewis Dyer <getintouch@icomefromthenet.com>
 * @since 1.0
 */ 
class RuleCreate 
{
    
    /**
     * @var VoucherGenRuleGateway
     */ 
    protected $oGateway;
    
    /**
     * @var DateTime
     */ 
    protected $oNow;
    
    
    /**
     * Class Constructor
     * 
     * @access public
     * @return void
     * @param VoucherGenRuleGateway    $oGateway   The Database Table Gateway
     * @param DateTime                 $oNow       The current datetime.
     */ 
    public function __construct(VoucherGenRuleGateway $oGateway, DateTime $oNow)
    {
        $this->oGateway = $oGateway;
        $this->oNow     = $oNow;
    }
    
    
    
    /**
     * Create a Voucher Rule
     * 
     * @param VoucherGenRule  $oVoucherRule  The Voucher Gen Rule
     * @throws VoucherException if the database query fails or entity has id assigned.
     * @returns boolean true if the insert operation was successful
     */ 
    public function execute(VoucherGenRule $oVoucheRule)
    {
        $oGateway        = $this->oGateway;
        $oBuilder = $oGateway->getEntityBuilder();
       
        if(true === empty($oVoucherGroup->getVoucherGenRuleId())) {
            throw new VoucherException('Unable to create new voucher rule the Entity requires a database id be assigned');
        }
    
        try {
        
            $oQuery = $oGateway->updateQuery()->start();
            
            foreach($oBuilder->demolish($oVoucherRule) as $sColumn => $mValue) {
                
                if($sColumn !== 'voucher_gen_rule_id' && $sColumn !== 'date_created') {
                    
                    $oQuery->addColumn($sColumn,$mValue);
                    
                } elseif($sColumn === 'date_created') {
                  
                    $oQuery->addColumn('date_created',$this->oNow);
                    
                }
                
            }
            
            $bSuccess = $oQuery>where()
                    ->filterByRule($oVoucherGroup->getVoucherGenRuleId())
                ->end()
                ->update(); 
    
            if($success) {
                $oVoucherGroup->setVoucherGenRuleI($gateway->lastInsertId());
            }
        
        }
        catch(DBALGatewayException $e) {
            throw new VoucherException($e->getMessage(),0,$e);
        }
        
        return $success;    
    }
    
}
/* End of Class */