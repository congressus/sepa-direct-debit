#SEPA SDD (Sepa Direct Debit) 2.0

<table>
<tr><td>Author:</td><td>Congressus, The Netherlands</td></tr> 
<tr><td>Date:</td><td>14-05-2013</td></tr> 
<tr><td>Description</td><td>A PHP class to create Sepa Direct Debit XML Files</td></tr> 
</table>
##1. INSTALLATION

SEPA SDD requires PHP 5, no other libraries are required.

To install, copy the SEPASDD.php class to a folder on the webserver and include it as follows:

```php
require_once([path_from_webroot_to_folder]/SEPASDD.php);
```

##2. CONFIGURATION

SEPA SSD requires a config array, which is validated on initiation.
The following parameters are required:

- name:		    The name of the creditor('s organization).
- IBAN: 	    The creditor's International Bank Account Number.
- BIC:		    The creditor's Bank Identification Code. (optional)
- batch:	    Whether to process as batch or as individual transactions .
		        Allowed: true or false (MUST be boolean).
- creditor_id:	The creditor's id, contact your bank if you do not know this.
- currency:	    The currency in which the amounts are defined. 
		    Allowed: ISO 4217.
- version:	    The PAIN.008.001 version. (optional)
	            Allowed: 3

#####Note:
When setting batch to true, SEPASDD will create a batch for each Direct Debit
transaction type ("FRST","RCUR", etc). and required collection date. This
means that all "FRST" transactions with collection date 2014-01-30 will be 
grouped in a batch.

####Example:

```php
$config = array("name" => "Test",
                "IBAN" => "NL50BANK1234567890",
                "BIC" => "BANKNL2A",
                "batch" => true,
                "creditor_id" => "00000",
                "currency" => "EUR"
                );
```

##3. USAGE

###3.1 Initialization

Create an instance of the class with an configuration as such:

```php
try{
    $SEPASDD = new SEPASDD($config);
}catch(Exception $e){
    echo $e->getMessage();
}
```

###3.2 Create a payment

SEPA SDD uses the addPayment method for creating payments, it requires a payment array.
The following parameters are required:

- name:			    The debtors name.
			    Allowed: 140 ASCII characters (ISO 20022 MaxText140)
- IBAN: 		    The debtor's International Bank Account Number.
			    Allowed: /[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[0-9]{7}([a-zA-Z0-9]?){0,16}/ (Note: This class does not perform check digit (index 2 and 3) validation. Please provide this yourself. 
- BIC:			    The debtor's Bank Identification Code. (optional)
			    Allowed: ([a-zA-Z]{4}[a-zA-Z]{2}[a-zA-Z0-9]{2}([a-zA-Z0-9]{3})?)
- amount:		    The amount to transfer from debtor to creditor (IN CENTS).
			        Allowed: int/string (NO SEPARATORS) e.g. EUR 10.00 has to be entered as 1000 or "1000"
- type:			    The type of Direct Debit Transaction
			        Allowed: FRST (First), RCUR (Recurring), OOFF (One Off), FNAL (Final)
- collection_date:  The date at which the amount should be collected from the debtor.
                    Allowed: ISO 8601 (YYYY-MM-DD). This date should be in the future, how far in
                             the future is dependent on the type of Direct Debit. See the definition.
- mandate_id:       The ID of the written mandate from the debtor.
		    Allowed: 35 ASCII characters. (ISO 20022 MaxText35)
- mandate_date:     The date the mandate was signed.
                    Allowed: ISO 8601 (YYYY-MM-DD). For mandates before SEPA requirements this is: 2009-11-01.
- description:      The description of the transaction.
		    Allowed: 140 ASCII characters (ISO 20022 MaxText140)

The following parameters are optional:

- end_to_end_id:    The unique (for ALL transactions ever done by creditor) id referencing this payment.
		    Allowed: 35 ASCII characters. (ISO 20022 MaxText35) 

####Example:

```php
$payment = array("name" => "Test von Testenstein",
                 "IBAN" => "NL50BANK1234567890",
                 "BIC" => "BANKNL2A",
                 "amount" => "1000",
                 "type" => "FRST",
                 "collection_date" => "2013-07-12",
                 "mandate_id" => "1234",
                 "mandate_date" => "2009-11-01",
                 "description" => "Test Transaction"
                );                
```

Then use the addPayment method to add the payment to the file:

####Example:

```php
try{
    $SEPASDD->addPayment($payment);
}catch(Exception $e){
    echo $e->getMessage();
}
```

You can use this method multiple times to add more payments.

###3.3 Save the file

To save the file, use the "save" method, this will return the XML as a string.
If you want to save to file, you have to do this yourself.

####Example:

```php
try{
    $SEPASDD->save();
}catch(Exception $e){
    echo $e->getMessage();
}
```

After this, please reinitialize the class if you want to create another file.

###3.4 Adding custom fields

SEPA SDD has a special method for adding custom fields. This method is called addCustomNode.
It is important to know that you will have to save the direct debit before and after calling this.
The required arguments are:

- parent_XPATH:     The XPATH selector of the parent.
- name:             The node/tag name.
- value:            Its value, default "".
- attr:             An array containing key => value pairs defining the attributes.

####Example:

Add the postal address for the last inserted Debtor. 

Caution, you should know what you are doing here.
Refer to the SEPA Direct Debit Core Rulebook and the XSD before using this. 

```php
$payment = array("name" => "Test von Testenstein",
                 "IBAN" => "NL50BANK1234567890",
                 "BIC" => "BANKNL2A",
                 "amount" => "1000",
                 "type" => "FRST",
                 "collection_date" => "2013-07-12",
                 "mandate_id" => "1234",
                 "mandate_date" => "2009-11-01",
                 "description" => "Test Transaction"
                );

try{
    $SEPASDD->addPayment($payment);
    $SEPASDD->save();
    $SEPASDD->addCustomNode("//Document/CstmrDrctDbtInitn/PmtInf[last()]/DrctDbtTxInf[last()]/Dbtr","PstlAdr");
    $SEPASDD->addCustomNode("//Document/CstmrDrctDbtInitn/PmtInf[last()]/DrctDbtTxInf[last()]/Dbtr/PstlAdr","AdrLine","Rode Weeshuisstraat 25");
    $SEPASDD->addCustomNode("//Document/CstmrDrctDbtInitn/PmtInf[last()]/DrctDbtTxInf[last()]/Dbtr/PstlAdr","AdrLine","9712 ET Groningen");
    $SEPASDD->save();
}catch(Exception $e){
    echo $e->getMessage();
}
```

Will create

```xml
<PstlAdr>
    <AdrLine>Rode Weeshuisstraat 25</AdrLine>
    <AdrLine>9712 ET Groningen</AdrLine>
</PstlAdr>
```

###3.5 Validation

To validate against the pain.008.001.02 schema definition, you can
use the validate() method. This requires the XML as string as argument.

#####Note: 
Some banks allow not specifying a BIC, and so does SEPASDD. However it is not
schema compliant and the schema WILL NOT validate.

####Example

```php
try{
    $xml = $SEPASDD->save();
    $validation = $SEPASDD->validate($xml);
    print_r($validation);
}catch(Exception $e){
    echo $e->getMessage();
}
```

##4 LICENSE

MIT LICENSE

 Copyright (c) 2013 Congressus, The Netherlands

 Permission is hereby granted, free of charge, to any person
 obtaining a copy of this software and associated documentation
 files (the "Software"), to deal in the Software without
 restriction, including without limitation the rights to use,
 copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the
 Software is furnished to do so, subject to the following
 conditions:

 The above copyright notice and this permission notice shall be
 included in all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 OTHER DEALINGS IN THE SOFTWARE. 

