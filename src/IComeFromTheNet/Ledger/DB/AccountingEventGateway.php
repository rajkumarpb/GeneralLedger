<?php
namespace IComeFromTheNet\Ledger\DB;

use DBALGateway\Table\AbstractTable;
use DBALGateway\Table\TableInterface;
use IComeFromTheNet\Ledger\Query\AccountingEventQuery;

/**
  *  AccountingEvents Table Gateway
  *
  *  Map entity to database
  *
  *  @author Lewis Dyer <getintouch@icomefromthenet.com>
  *  @since 1.0.0
  */
class AccountingEventGateway extends AbstractTable implements TableInterface 
{
    
    /**
      *  Create a new instance of the querybuilder
      *
      *  @access public
      *  @return IComeFromTheNet\Ledger\Query\AccountingEventQuery
      */
    public function newQueryBuilder()
    {
        return new AccountingEventQuery($this->adapter,$this);
    }
    
    
}
