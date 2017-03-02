<?php
namespace Congressus\SepaDirectDebit;

/*
 Copyright (c) 2016 Congressus, The Netherlands

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
*/

/**
 * SEPA SSD (Sepa Direct Debit) 2.1.1
 * This class creates a Sepa Direct Debit XML File.
 */
class SEPASDD
{
    /**
     * @var array
     */
    private $config = array();
    
    /**
     * @var \DOMDocument
     */
    private $xml;
    
    /**
     * @var array
     */
    private $batchArray = array();
    
    /**
     * SepaService constructor.
     *
     * @param array $config
     *
     * @throws \Exception
     */
    public function __construct(array $config = array())
    {
        //Check the config
        $this->config = $config;
        $configValidator = $this->validateConfig($config);
        
        if ($configValidator !== true) {
            throw new \Exception('Invalid config file: ' . $configValidator);
        }
        
        //Prepare the document
        $this->prepareDocument();
        $this->createGroupHeader();
    }
    
    /**
     * Build the main document node and set xml namespaces.
     *
     * @return void
     */
    private function prepareDocument()
    {
        //Create the XML Instance
        $this->xml = new \DOMDocument('1.0', 'UTF-8');
        
        //Set formatting options
        $this->xml->preserveWhiteSpace = false;
        $this->xml->formatOutput = true;
        
        //Create the document node
        $documentNode = $this->xml->createElement('Document');
        
        //set the namespace
        $documentAttributeXMLNS = $this->xml->createAttribute('xmlns');
        if (isset($this->config['version']) && $this->config['version'] == '3') {
            $documentAttributeXMLNS->value = 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.03';
        } else {
            $documentAttributeXMLNS->value = 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02';
        }
        $documentNode->appendChild($documentAttributeXMLNS);
        
        //set the namespace url
        $documentAttributeXMLNSXSI = $this->xml->createAttribute('xmlns:xsi');
        $documentAttributeXMLNSXSI->value = 'http://www.w3.org/2001/XMLSchema-instance';
        $documentNode->appendChild($documentAttributeXMLNSXSI);
        
        //create the Direct Debit node
        $cstmrDrctDbtInitnNode = $this->xml->createElement('CstmrDrctDbtInitn');
        $documentNode->appendChild($cstmrDrctDbtInitnNode);
        
        //append the document node to the XML Instance
        $this->xml->appendChild($documentNode);
    }
    
    /**
     * Function to create the GroupHeader (GrpHdr) in the CstmrDrctDbtInit Node
     *
     * @return void
     */
    private function createGroupHeader()
    {
        //Retrieve the CstmrDrctDbtInitn node
        $cstmrDrctDbtInitnNode = $this->getCstmrDrctDbtInitnNode();
        
        //Create the required nodes
        $grpHdrNode     = $this->xml->createElement('GrpHdr');
        $msgIdNode      = $this->xml->createElement('MsgId');
        $creDtTmNode    = $this->xml->createElement('CreDtTm');
        $nbOfTxsNode    = $this->xml->createElement('NbOfTxs');
        $ctrlSumNode    = $this->xml->createElement('CtrlSum');
        $initgPtyNode   = $this->xml->createElement('InitgPty');
        $nmNode         = $this->xml->createElement('Nm');
        
        //Set the values for the nodes
        $msgIdNode->nodeValue = $this->makeMsgId();
        $creDtTmNode->nodeValue = date('Y-m-d\TH:i:s', time());
        
        //If using lower than PHP 5.4.0, there is no ENT_XML1
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $nmNode->nodeValue = htmlentities($this->config['name'], ENT_XML1, 'UTF-8');
        } else {
            $nmNode->nodeValue = htmlentities($this->config['name'], ENT_QUOTES, 'UTF-8');
        }
        
        //Append the nodes
        $initgPtyNode->appendChild($nmNode);
        $grpHdrNode->appendChild($msgIdNode);
        $grpHdrNode->appendChild($creDtTmNode);
        $grpHdrNode->appendChild($nbOfTxsNode);
        $grpHdrNode->appendChild($ctrlSumNode);
        $grpHdrNode->appendChild($initgPtyNode);
        
        //Append the header to its parent
        $cstmrDrctDbtInitnNode->appendChild($grpHdrNode);
    }
    
    /**
     * Public function to add payments
     *
     * @param array $payment The payment to be added in the form of an array
     *
     * @return mixed
     *
     * @throws \Exception if payment array is invalid.
     */
    public function addPayment(array $payment)
    {
        //First validate the payment array
        $validationResult = $this->validatePayment($payment);
        if ($validationResult !== true) {
            throw new \Exception('Invalid Payment, error with: ' . $validationResult);
        }
        
        $batch = array();
    
        /** @var \DOMElement $pmtInfNode */
        $pmtInfNode            = null;
        /** @var \DOMElement $pmtInfIdNode */
        $pmtInfIdNode          = null;
        /** @var \DOMElement $pmtMtdNode */
        $pmtMtdNode            = null;
        /** @var \DOMElement $btchBookgNode */
        $btchBookgNode         = null;
        /** @var \DOMElement $nbOfTxsNode */
        $nbOfTxsNode           = null;
        /** @var \DOMElement $ctrlSumNode */
        $ctrlSumNode           = null;
        /** @var \DOMElement $pmtTpInfNode */
        $pmtTpInfNode          = null;
        /** @var \DOMElement $svcLvlNode */
        $svcLvlNode            = null;
        /** @var \DOMElement $cdSvcLvlNode */
        $cdSvcLvlNode          = null;
        /** @var \DOMElement $lclInstrmNode */
        $lclInstrmNode         = null;
        /** @var \DOMElement $cdLclInstrmNode */
        $cdLclInstrmNode       = null;
        /** @var \DOMElement $seqTpNode */
        $seqTpNode             = null;
        /** @var \DOMElement $reqdColltnDtNode */
        $reqdColltnDtNode      = null;
        /** @var \DOMElement $cdtrNode */
        $cdtrNode              = null;
        /** @var \DOMElement $nmCdtrNode */
        $nmCdtrNode            = null;
        /** @var \DOMElement $cdtrAcctNode */
        $cdtrAcctNode          = null;
        /** @var \DOMElement $idCdtrAcctNode */
        $idCdtrAcctNode        = null;
        /** @var \DOMElement $iBanCdtrAcctNode */
        $iBanCdtrAcctNode      = null;
        /** @var \DOMElement $cdtrAgtNode */
        $cdtrAgtNode           = null;
        /** @var \DOMElement $finInstnIdCdtrAgtNode */
        $finInstnIdCdtrAgtNode = null;
        /** @var \DOMElement $bicCdtrAgtNode */
        $bicCdtrAgtNode        = null;
        /** @var \DOMElement $idOthrCdtrAgtNode */
        $idOthrCdtrAgtNode     = null;
        /** @var \DOMElement $othrCdtrAgtNode */
        $othrCdtrAgtNode       = null;
        /** @var \DOMElement $chrgBrNode */
        $chrgBrNode            = null;
        /** @var \DOMElement $cdtrSchmeIdNode */
        $cdtrSchmeIdNode       = null;
        /** @var \DOMElement $nmCdtrSchmeIdNode */
        $nmCdtrSchmeIdNode     = null;
        /** @var \DOMElement $idCdtrSchmeIdNode */
        $idCdtrSchmeIdNode     = null;
        /** @var \DOMElement $prvtIdNode */
        $prvtIdNode            = null;
        /** @var \DOMElement $othrNode */
        $othrNode              = null;
        /** @var \DOMElement $idOthrNode */
        $idOthrNode            = null;
        /** @var \DOMElement $schmeNmNode */
        $schmeNmNode           = null;
        /** @var \DOMElement $prtryNode */
        $prtryNode             = null;
        /** @var \DOMElement $bicDbtrAgtNode */
        $bicDbtrAgtNode        = null;
        /** @var \DOMElement $idOthrDbtrAgtNode */
        $idOthrDbtrAgtNode     = null;
        /** @var \DOMElement $othrDbtrAgtNode */
        $othrDbtrAgtNode       = null;
    
        //Get the CstmrDrctDbtInitnNode
        $cstmrDrctDbtInitnNode  = $this->getCstmrDrctDbtInitnNode();
        
        //If there is a batch, the batch will create this information.
        if ($this->config['batch'] == false) {
            $pmtInfNode            = $this->xml->createElement('PmtInf');
            $pmtInfIdNode          = $this->xml->createElement('PmtInfId');
            $pmtMtdNode            = $this->xml->createElement('PmtMtd');
            $btchBookgNode         = $this->xml->createElement('BtchBookg');
            $nbOfTxsNode           = $this->xml->createElement('NbOfTxs');
            $ctrlSumNode           = $this->xml->createElement('CtrlSum');
            $pmtTpInfNode          = $this->xml->createElement('PmtTpInf');
            $svcLvlNode            = $this->xml->createElement('SvcLvl');
            $cdSvcLvlNode        = $this->xml->createElement('Cd');
            $lclInstrmNode         = $this->xml->createElement('LclInstrm');
            $cdLclInstrmNode       = $this->xml->createElement('Cd');
            $seqTpNode             = $this->xml->createElement('SeqTp');
            $reqdColltnDtNode      = $this->xml->createElement('ReqdColltnDt');
            $cdtrNode              = $this->xml->createElement('Cdtr');
            $nmCdtrNode            = $this->xml->createElement('Nm');
            $cdtrAcctNode          = $this->xml->createElement('CdtrAcct');
            $idCdtrAcctNode        = $this->xml->createElement('Id');
            $iBanCdtrAcctNode      = $this->xml->createElement('IBAN');
            $cdtrAgtNode           = $this->xml->createElement('CdtrAgt');
            $finInstnIdCdtrAgtNode = $this->xml->createElement('FinInstnId');
    
            if (isset($this->config['BIC'])) {
                if (isset($this->config['version']) && $this->config['version'] == '3') {
                    $bicCdtrAgtNode = $this->xml->createElement('BICFI');
                } else {
                    $bicCdtrAgtNode = $this->xml->createElement('BIC');
                }
            } else {
                $othrCdtrAgtNode   = $this->xml->createElement('Othr');
                $idOthrCdtrAgtNode = $this->xml->createElement('Id');
            }
            $chrgBrNode        = $this->xml->createElement('ChrgBr');
            $cdtrSchmeIdNode   = $this->xml->createElement('CdtrSchmeId');
            $nmCdtrSchmeIdNode = $this->xml->createElement('Nm');
            $idCdtrSchmeIdNode = $this->xml->createElement('Id');
            $prvtIdNode        = $this->xml->createElement('PrvtId');
            $othrNode          = $this->xml->createElement('Othr');
            $idOthrNode        = $this->xml->createElement('Id');
            $schmeNmNode       = $this->xml->createElement('SchmeNm');
            $prtryNode         = $this->xml->createElement('Prtry');
            
            $pmtInfIdNode->nodeValue     = $this->makeId();
            $pmtMtdNode->nodeValue       = 'DD'; //Direct Debit
            $btchBookgNode->nodeValue    = 'false';
            $nbOfTxsNode->nodeValue      = '1';
            $ctrlSumNode->nodeValue      = $this->intToDecimal($payment['amount']);
            $cdSvcLvlNode->nodeValue   = 'SEPA';
            $cdLclInstrmNode->nodeValue  = 'CORE';
            $seqTpNode->nodeValue        = $payment['type']; //Define a check for: FRST RCUR OOFF FNAL
            $reqdColltnDtNode->nodeValue = $payment['collection_date'];
            
            if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                $nmCdtrNode->nodeValue = htmlentities($this->config['name'], ENT_XML1, 'UTF-8');
            } else {
                $nmCdtrNode->nodeValue = htmlentities($this->config['name'], ENT_QUOTES, 'UTF-8');
            }
            
            $iBanCdtrAcctNode->nodeValue = $this->config['IBAN'];
            if (isset($this->config['BIC'])) {
                $bicCdtrAgtNode->nodeValue = $this->config['BIC'];
            } else {
                $idOthrCdtrAgtNode->nodeValue = 'NOTPROVIDED';
            }
            $chrgBrNode->nodeValue = 'SLEV';
            
            if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                $nmCdtrSchmeIdNode->nodeValue = htmlentities($this->config['name'], ENT_XML1, 'UTF-8');
            } else {
                $nmCdtrSchmeIdNode->nodeValue = htmlentities($this->config['name'], ENT_QUOTES, 'UTF-8');
            }
            
            $idOthrNode->nodeValue = $this->config['creditor_id'];
            $prtryNode->nodeValue  = 'SEPA';
        } else {
            //Get the batch node for this kind of payment to add the DrctDbtTxInf node.
            $batch = $this->getBatch($payment['type'], $payment['collection_date']);
        }
        
        //Create the payment node.
        $drctDbtTxInfNode      = $this->xml->createElement('DrctDbtTxInf');
        $pmtIdNode             = $this->xml->createElement('PmtId');
        $endToEndIdNode        = $this->xml->createElement('EndToEndId');
        $instdAmtNode          = $this->xml->createElement('InstdAmt');
        $drctDbtTxNode         = $this->xml->createElement('DrctDbtTx');
        $mndtRltdInfNode       = $this->xml->createElement('MndtRltdInf');
        $mndtIdNode            = $this->xml->createElement('MndtId');
        $dtOfSgntrNode         = $this->xml->createElement('DtOfSgntr');
        $dbtrAgtNode           = $this->xml->createElement('DbtrAgt');
        $finInstnIdDbtrAgtNode = $this->xml->createElement('FinInstnId');
    
        if (isset($payment['BIC'])) {
            if (isset($this->config['version']) && $this->config['version'] == '3') {
                $bicDbtrAgtNode = $this->xml->createElement('BICFI');
            } else {
                $bicDbtrAgtNode = $this->xml->createElement('BIC');
            }
        } else {
            $othrDbtrAgtNode   = $this->xml->createElement('Othr');
            $idOthrDbtrAgtNode = $this->xml->createElement('Id');
        }
        $dbtrNode         = $this->xml->createElement('Dbtr');
        $nmDbtrNode       = $this->xml->createElement('Nm');
        $dbtrAcctNode     = $this->xml->createElement('DbtrAcct');
        $idDbtrAcctNode   = $this->xml->createElement('Id');
        $iBanDbtrAcctNode = $this->xml->createElement('IBAN');
        $rmtInfNode       = $this->xml->createElement('RmtInf');
        $ustrdNode        = $this->xml->createElement('Ustrd');
        
        //Set the payment node information
        $instdAmtNode->setAttribute('Ccy', $this->config['currency']);
        $instdAmtNode->nodeValue = $this->intToDecimal($payment['amount']);
        
        $mndtIdNode->nodeValue    = $payment['mandate_id'];
        $dtOfSgntrNode->nodeValue = $payment['mandate_date'];
        
        if (isset($payment['BIC'])) {
            $bicDbtrAgtNode->nodeValue = $payment['BIC'];
        } else {
            $idOthrDbtrAgtNode->nodeValue = 'NOTPROVIDED';
        }
        
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $nmDbtrNode->nodeValue    = htmlentities($payment['name'], ENT_XML1, 'UTF-8');
        } else {
            $nmDbtrNode->nodeValue    = htmlentities($payment['name'], ENT_QUOTES, 'UTF-8');
        }
        
        $iBanDbtrAcctNode->nodeValue  = $payment['IBAN'];
        
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $ustrdNode->nodeValue = htmlentities($payment['description'], ENT_XML1, 'UTF-8');
        } else {
            $ustrdNode->nodeValue = htmlentities($payment['description'], ENT_QUOTES, 'UTF-8');
        }
        
        $endToEndIdNode->nodeValue = (empty($payment['end_to_end_id']) ? $this->makeId() : $payment['end_to_end_id']);
        
        //Fold the nodes, if batch is enabled, some of this will be done by the batch.
        if ($this->config['batch'] == false) {
            $pmtInfNode->appendChild($pmtInfIdNode);
            $pmtInfNode->appendChild($pmtMtdNode);
            $pmtInfNode->appendChild($btchBookgNode);
            $pmtInfNode->appendChild($nbOfTxsNode);
            $pmtInfNode->appendChild($ctrlSumNode);
            
            $svcLvlNode->appendChild($cdSvcLvlNode);
            $pmtTpInfNode->appendChild($svcLvlNode);
            $lclInstrmNode->appendChild($cdLclInstrmNode);
            $pmtTpInfNode->appendChild($lclInstrmNode);
            $pmtTpInfNode->appendChild($seqTpNode);
            $pmtInfNode->appendChild($pmtTpInfNode);
            $pmtInfNode->appendChild($reqdColltnDtNode);
            
            $cdtrNode->appendChild($nmCdtrNode);
            $pmtInfNode->appendChild($cdtrNode);
            
            $idCdtrAcctNode->appendChild($iBanCdtrAcctNode);
            $cdtrAcctNode->appendChild($idCdtrAcctNode);
            $pmtInfNode->appendChild($cdtrAcctNode);
            
            if (isset( $config['BIC'] )) {
                $finInstnIdCdtrAgtNode->appendChild($bicCdtrAgtNode);
            } else {
                $othrCdtrAgtNode->appendChild($idOthrCdtrAgtNode);
                $finInstnIdCdtrAgtNode->appendChild($othrCdtrAgtNode);
            }
            $cdtrAgtNode->appendChild($finInstnIdCdtrAgtNode);
            $pmtInfNode->appendChild($cdtrAgtNode);
            
            $pmtInfNode->appendChild($chrgBrNode);
            
            $cdtrSchmeIdNode->appendChild($nmCdtrSchmeIdNode);
            $othrNode->appendChild($idOthrNode);
            $schmeNmNode->appendChild($prtryNode);
            $othrNode->appendChild($schmeNmNode);
            $prvtIdNode->appendChild($othrNode);
            $idCdtrSchmeIdNode->appendChild($prvtIdNode);
            $cdtrSchmeIdNode->appendChild($idCdtrSchmeIdNode);
            $pmtInfNode->appendChild($cdtrSchmeIdNode);
        }
        $pmtIdNode->appendChild($endToEndIdNode);
        
        $drctDbtTxInfNode->appendChild($pmtIdNode);
        $drctDbtTxInfNode->appendChild($instdAmtNode);
        
        $mndtRltdInfNode->appendChild($mndtIdNode);
        $mndtRltdInfNode->appendChild($dtOfSgntrNode);
        $drctDbtTxNode->appendChild($mndtRltdInfNode);
        $drctDbtTxInfNode->appendChild($drctDbtTxNode);
        
        if (isset( $payment['BIC'] )) {
            $finInstnIdDbtrAgtNode->appendChild($bicDbtrAgtNode);
        } else {
            $othrDbtrAgtNode->appendChild($idOthrDbtrAgtNode);
            $finInstnIdDbtrAgtNode->appendChild($othrDbtrAgtNode);
        }
        $dbtrAgtNode->appendChild($finInstnIdDbtrAgtNode);
        $drctDbtTxInfNode->appendChild($dbtrAgtNode);
        $dbtrNode->appendChild($nmDbtrNode);
        $drctDbtTxInfNode->appendChild($dbtrNode);
        
        $idDbtrAcctNode->appendChild($iBanDbtrAcctNode);
        $dbtrAcctNode->appendChild($idDbtrAcctNode);
        $drctDbtTxInfNode->appendChild($dbtrAcctNode);
        
        $rmtInfNode->appendChild($ustrdNode);
        $drctDbtTxInfNode->appendChild($rmtInfNode);
        
        $pmtIdNode->appendChild($endToEndIdNode);
        
        if ($this->config['batch'] == false) {
            //Add to the document
            $pmtInfNode->appendChild($drctDbtTxInfNode);
            $cstmrDrctDbtInitnNode->appendChild($pmtInfNode);
        } else {
            //Update the batch metrics
            $batch['ctrlSum']->nodeValue = str_replace('.', '', $batch['ctrlSum']->nodeValue); #For multiple saves
            $batch['ctrlSum']->nodeValue += $payment['amount'];
            $batch['nbOfTxs']->nodeValue++;
            
            //Add to the batch
            $batch['node']->appendChild($drctDbtTxInfNode);
        }
        
        return $endToEndIdNode->nodeValue;
    }
    
    /**
     * Function to finalize and save the document after all payments are added.
     *
     * @return string The XML to be echoed or saved to file. False on error
     */
    public function save()
    {
        $this->finalize();
        return $this->xml->saveXML();
    }
    
    /**
     * Function to validate xml against the pain.008.001.02 schema definition.
     *
     * @param string $xml The xml, as a string, to validate agianst the schema.
     *
     * @return bool
     */
    public function validate($xml)
    {
        $domDoc = new \DOMDocument();
        $domDoc->loadXML($xml);
        if (isset($this->config['version']) && $this->config['version'] == '3') {
            return $domDoc->schemaValidate('pain.008.001.03.xsd');
        } else {
            return $domDoc->schemaValidate('pain.008.001.02.xsd');
        }
    }
    
    /**
     * Function to add a custom node to the document.
     *
     * @param string $parentXpath A valid XPATH expression defining the parent of the new node
     * @param string $name The name of the new node
     * @param string $value The value of the new node (Optional, default '')
     * @param array $attr Key => Value array defining the attributes (Optional, default none)
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addCustomNode($parentXpath, $name, $value = '', array $attr = array())
    {
        $xpath = new \DOMXPath($this->xml);
        $parent = $xpath->query($parentXpath);
        if ($parent == false || $parent->length == 0) {
            throw new \Exception('Invalid XPATH expression, or no results found: '.$parentXpath);
        }
        $newNode = $this->xml->createElement($name);
        if ($value != '') {
            $newNode->nodeValue = $value;
        }
        if (!empty($attr)) {
            foreach ($attr as $attrName => $attrValue) {
                $newNode->setAttribute($attrName, $attrValue);
            }
        }
        $parent->item(0)->appendChild($newNode);
    }
    
    /**
     * Function to finalize the document, completes the header with metadata, and processes batches.
     *
     * @return void
     */
    private function finalize()
    {
        if (!empty($this->batchArray)) {
            $cstmrDrctDbtInitnNode = $this->getCstmrDrctDbtInitnNode();
            foreach ($this->batchArray as $batch) {
                $batch['ctrlSum']->nodeValue = $this->intToDecimal($batch['ctrlSum']->nodeValue);
                $cstmrDrctDbtInitnNode->appendChild($batch['node']);
            }
        }
        
        $trxCount = $this->xml->getElementsByTagName('DrctDbtTxInf');
        $trxCount = $trxCount->length;
        $trxAmounts = $this->xml->getElementsByTagName('InstdAmt');
        $trxAmountArray = array();
        foreach ($trxAmounts as $amount) {
            $trxAmountArray[] = $amount->nodeValue;
        }
        $trxAmount = $this->calcTotalAmount($trxAmountArray);
        $xpath = new \DOMXPath($this->xml);
        $nbOfTxsXpath = '//Document/CstmrDrctDbtInitn/GrpHdr/NbOfTxs';
        $ctrlSumXpath = '//Document/CstmrDrctDbtInitn/GrpHdr/CtrlSum';
        $nbOfTxsNode = $xpath->query($nbOfTxsXpath)->item(0);
        $ctrlSumNode = $xpath->query($ctrlSumXpath)->item(0);
        
        $nbOfTxsNode->nodeValue = $trxCount;
        $ctrlSumNode->nodeValue = $trxAmount;
    }
    
    /**
     * Check the config file for required fields and validity.
     * NOTE: A function entry in this field will NOT be evaluated if the field is not present in the
     * config array. If this is necessary, please include it in the $required array as well.
     *
     * @param array $config the config to check.
     *
     * @return bool|string true if valid. String if invalid.
     */
    private function validateConfig(array $config)
    {
        $required = array('name', 'IBAN', 'batch', 'creditor_id', 'currency');
        $functions = array(
            'IBAN' => 'validateIBAN',
            'BIC' => 'validateBIC',
            'batch' => 'validateBatch'
       );
        
        foreach ($required as $requirement) {
            //Check if the config has the required parameter
            if (array_key_exists($requirement, $config)) {
                //It exists, check if not empty
                if ($config[$requirement] !== false && empty($config[$requirement])) {
                    return $requirement . ' is empty.';
                }
            } else {
                return $requirement . ' does not exist.';
            }
            
        }
        
        foreach ($functions as $target => $function) {
            //Check if it is even there in the config
            if (array_key_exists($target, $config)) {
                //Perform the validation
                $functionResult = call_user_func('SELF::' . $function,$config[$target]);
                if ($functionResult) {
                    continue;
                } else {
                    return $target . ' does not validate.';
                }
            }
        }
        
        return true;
    }
    
    /**
     * Check a payment for validity
     *
     * @param array $payment The payment array
     *
     * @return bool|string true if valid, error string if invalid.
     */
    private function validatePayment(array $payment)
    {
        $required = array(
            'name',
            'IBAN',
            'amount',
            'type',
            'collection_date',
            'mandate_id',
            'mandate_date',
            'description'
        );
        
        $functions = array(
            'IBAN' => 'validateIBAN',
            'BIC' => 'validateBIC',
            'amount' => 'validateAmount',
            'collection_date' => 'validateDate',
            'mandate_date' => 'validateMandateDate',
            'type' => 'validateDDType',
            'end_to_end_id' => 'validateEndToEndId'
        );
        
        foreach ($required as $requirement) {
            //Check if the config has the required parameter
            if (array_key_exists($requirement, $payment)) {
                //It exists, check if not empty
                if (empty($payment[$requirement])) {
                    return $requirement . ' is empty.';
                }
            } else {
                return $requirement . ' does not exist.';
            }
        }
        
        foreach ($functions as $target => $function) {
            //Check if it is even there in the config
            if (array_key_exists($target, $payment)) {
                //Perform the RegEx
                $functionResult = call_user_func('SELF::' . $function,$payment[$target]);
                if ($functionResult === true) {
                    continue;
                } else {
                    return $target . ' does not validate: ' . $functionResult;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Validate an batch config option.
     *
     * @param bool $batch the boolean to check.
     *
     * @return bool true if valid, false if invalid.
     */
    public static function validateBatch($batch)
    {
        return is_bool($batch);
    }
    
    /**
     * Validate an IBAN Number.
     *
     * @param string $iBan The IBAN number to check.
     *
     * @return bool true if valid, false if invalid.
     */
    public function validateIBAN($iBan)
    {
        if (array_key_exists('validate', $this->config) && $this->config['validate'] == false) {
            return true;
        }
        $result = preg_match('/[A-Z]{2,2}[0-9]{2,2}[a-zA-Z0-9]{1,30}/', $iBan);
        if ($result == 0 || $result === false) {
            return false;
        }
        
        $indexArray = array_flip(['0','1','2','3','4','5','6','7','8','9','A','B','C',
            'D','E','F','G','H','I','J','K','L','M','N','O','P',
            'Q','R','S','T','U','V','W','X','Y','Z']
        );
    
        $iBan = strtoupper($iBan);
        $iBan = substr($iBan, 4) . substr($iBan, 0, 4); // Place CC and Check at back
        
        $iBanArray = str_split($iBan);
        $iBanDecimal = '';
        foreach ($iBanArray as $char) {
            $iBanDecimal .= $indexArray[$char]; //Convert the iban to decimals
        }
        
        //To avoid the big number issues, we split the modulus into iterations.
        
        //First chunk is 9, the rest are modulus (max 2) + 7, last one is whatever is left (2 + < 7).
        $startChunk = substr($iBanDecimal, 0, 9);
        $startMod = intval($startChunk) % 97;
        
        $iBanDecimal = substr($iBanDecimal, 9);
        $chunks = ceil(strlen($iBanDecimal) / 7);
        $remainder = strlen($iBanDecimal) % 7;
        
        for($i = 0; $i <= $chunks; $i++) {
            $iBanDecimal = $startMod . $iBanDecimal;
            $startChunk = substr($iBanDecimal, 0, 7);
            $startMod = intval($startChunk) % 97;
            $iBanDecimal = substr($iBanDecimal, 7);
        }
        
        //Check if we have a chunk with less than 7 numbers.
        if ($remainder != 0) {
            $endMod = (int)$startMod . (int)$iBanDecimal % 97;
        } else {
            $endMod = $startMod;
        }
        return $endMod == 1;
    }
    
    /**
     * Validate an EndToEndId.
     *
     * @param string $endToEndId the EndToEndId to check.
     *
     * @return bool|string true if valid, error string if invalid.
     */
    public static function validateEndToEndId($endToEndId)
    {
        $ascii = mb_check_encoding($endToEndId, 'ASCII');
        $len = strlen($endToEndId);
        if ($ascii && $len < 36) {
            return true;
        } else if (!$ascii) {
            return $endToEndId . ' is not ASCII';
        } else {
            return $endToEndId . ' is longer than 35 characters';
        }
    }
    
    /**
     * Validate a BIC number.Payment Information
     *
     * @param string $bic the BIC number to check.
     *
     * @return true if valid, false if invalid.
     */
    public function validateBIC($bic)
    {
        if (array_key_exists('validate', $this->config) && $this->config['validate'] == false) {
            return true;
        }
        $result = preg_match('([a-zA-Z]{4}[a-zA-Z]{2}[a-zA-Z0-9]{2}([a-zA-Z0-9]{3})?)', $bic);
        return $result > 0 && $result !== false;
    }
    
    /**
     * Function to validate a ISO date.
     *
     * @param string $date The date to validate.
     *
     * @return bool|string true if valid, error string if invalid.
     */
    public static function validateDate($date)
    {
        $result = \DateTime::createFromFormat('Y-m-d',$date);
        
        if ($result === false) {
            return $date . ' is not a valid ISO Date';
        }
        
        return true;
    }
    
    /**
     * Function to validate a ISO date.
     *
     * @param string $date The date to validate.
     *
     * @return bool|string true if valid, error string if invalid.
     */
    public static function validateMandateDate($date)
    {
        $result = \DateTime::createFromFormat('Y-m-d', $date);
        
        if ($result === false) {
            return $date . ' is not a valid ISO Date';
        }
        
        $timeStamp = $result->getTimestamp();
        $beginOfToday = strtotime(date('Y-m-d') . ' 00:00');
        
        if ($timeStamp > $beginOfToday) {
            return sprintf(
                'mandate_date %s must be at least 1 day earlier then current day %s',
                $date,
                date('Y-m-d')
            );
        }
        
        return true;
    }
    
    /**
     * Function to validate the Direct Debit Transaction types
     *
     * @param string $type Typecode
     *
     * @return bool|string true if valid, error string if invalid.
     */
    public static function validateDDType($type)
    {
        $types = array('FRST', 'RCUR', 'FNAL', 'OOFF');
        if (in_array($type, $types)) {
            return true;
        } else {
            return $type . ' is not a valid Sepa Direct Debit Transaction Type.';
        }
    }
    
    /**
     * Function to validate an amount, to check that amount is in cents.
     *
     * @param string $amount The amount to validate.
     *
     * @return bool true if valid, false if invalid.
     */
    public static function validateAmount($amount)
    {
        return ctype_digit((string)$amount);
    }
    
    /**
     * Function to convert an amount in cents to a decimal (with point).
     *
     * @param int $int The amount as decimal string
     *
     * @return string The decimal
     */
    private function intToDecimal($int)
    {
        $int = str_replace('.', '', $int); //For cases where the int is already an decimal.
        $before = substr($int, 0, -2);
        $after = substr($int, -2);
        if (empty($before)) {
            $before = 0;
        }
        if (strlen($after) == 1) {
            $after = '0' . $after;
        }
        return $before . '.' . $after;
    }
    
    /**
     * Function to convert an amount in decimal to cents (without point).
     *
     * @param string $decimal The amount as decimal
     *
     * @return string The amount as integer string
     */
    private function decimalToInt($decimal)
    {
        return str_replace('.', '', $decimal);
    }
    
    /**
     * Function to calculate the sum of the amounts, given as decimals in an array.
     *
     * @param array $values The array with decimals
     *
     * @return string The decimal sum of the array
     */
    private function calcTotalAmount(array $values)
    {
        $intValues = array();
        foreach ($values as $decimal) {
            $intValues[] = $this->decimalToInt($decimal);
        }
        $sum = array_sum($intValues);
        return $this->intToDecimal($sum);
    }
    
    /**
     * Create a random Message Id f$PmtInfNodeor the header, prefixed with a timestamp.
     *
     * @return string The Message Id.
     */
    private function makeMsgId()
    {
        $random = mt_rand();
        $random = md5($random);
        $random = substr($random, 0, 12);
        $timestamp = date('dmYsi');
        return $timestamp . '-' . $random;
    }
    
    /**
     * Create a random id, combined with the name (truncated at 22 chars).
     *
     * @return string The Id.
     */
    private function makeId()
    {
        $random = mt_rand();
        $random = md5($random);
        $random = substr($random, 0, 12);
        $name = $this->config['name'];
        $length = strlen($name);
        if ($length > 22) {
            $name = substr($name, 0, 22);
        }
        return $name . '-' . $random;
    }
    
    /**
     * Function to get the CstmrDrctDbtInitnNode from the current document.
     *
     * @return \DOMElement The CstmrDrctDbtInitn DOMNode.
     *
     * @throws \Exception when the node does not exist or there are more then one.
     */
    private function getCstmrDrctDbtInitnNode()
    {
        $cstmrDrctDbtInitnNodeList = $this->xml->getElementsByTagName('CstmrDrctDbtInitn');
        if ($cstmrDrctDbtInitnNodeList->length != 1) {
            throw new \Exception('Error retrieving node from document: No or Multiple CstmrDrctDbtInitn');
        }
        return $cstmrDrctDbtInitnNodeList->item(0);
    }
    
    /**
     * Function to create a batch (PmtInf with BtchBookg set true) element.
     *
     * @param string $type The DirectDebit type for this batch.
     * @param string $date The required collection date.
     *
     * @return array
     */
    private function getBatch($type, $date)
    {
        //If the batch for this type and date already exists, return it.
        if ($this->validateDDType($type) &&
            $this->validateDate($date) &&
            array_key_exists($type . '::' . $date, $this->batchArray)
       ) {
            return $this->batchArray[$type . '::' . $date];
        }
        
        //Create the PmtInf element and its subelements
        $pmtInfNode            = $this->xml->createElement('PmtInf');
        $pmtInfIdNode          = $this->xml->createElement('PmtInfId');
        $pmtMtdNode            = $this->xml->createElement('PmtMtd');
        $btchBookgNode         = $this->xml->createElement('BtchBookg');
        $nbOfTxsNode           = $this->xml->createElement('NbOfTxs');
        $ctrlSumNode           = $this->xml->createElement('CtrlSum');
        $pmtTpInfNode          = $this->xml->createElement('PmtTpInf');
        $svcLvlNode            = $this->xml->createElement('SvcLvl');
        $cdSvcLvlNode          = $this->xml->createElement('Cd');
        $lclInstrmNode         = $this->xml->createElement('LclInstrm');
        $cdLclInstrmNode       = $this->xml->createElement('Cd');
        $seqTpNode             = $this->xml->createElement('SeqTp');
        $reqdColltnDtNode      = $this->xml->createElement('ReqdColltnDt');
        $cdtrNode              = $this->xml->createElement('Cdtr');
        $nmCdtrNode            = $this->xml->createElement('Nm');
        $cdtrAcctNode          = $this->xml->createElement('CdtrAcct');
        $idCdtrAcctNode        = $this->xml->createElement('Id');
        $iBanCdtrAcctNode      = $this->xml->createElement('IBAN');
        $cdtrAgtNode           = $this->xml->createElement('CdtrAgt');
        $finInstnIdCdtrAgtNode = $this->xml->createElement('FinInstnId');
    
        /** @var \DOMElement $bicCdtrAgtNode */
        $bicCdtrAgtNode = null;
        /** @var \DOMElement $idOthrCdtrAgtNode */
        $idOthrCdtrAgtNode = null;
        /** @var \DOMElement $othrCdtrAgtNode */
        $othrCdtrAgtNode = null;
        
        if (isset($this->config['BIC'])) {
            if (isset($this->config['version']) && $this->config['version'] == '3') {
                $bicCdtrAgtNode = $this->xml->createElement('BICFI');
            } else {
                $bicCdtrAgtNode = $this->xml->createElement('BIC');
            }
        } else {
            $othrCdtrAgtNode   = $this->xml->createElement('Othr');
            $idOthrCdtrAgtNode = $this->xml->createElement('Id');
        }
        $chrgBrNode             = $this->xml->createElement('ChrgBr');
        $cdtrSchmeIdNode        = $this->xml->createElement('CdtrSchmeId');
        $nmCdtrSchmeIdNode      = $this->xml->createElement('Nm');
        $idCdtrSchmeIdNode      = $this->xml->createElement('Id');
        $prvtIdNode             = $this->xml->createElement('PrvtId');
        $othrNode               = $this->xml->createElement('Othr');
        $idOthrNode             = $this->xml->createElement('Id');
        $schmeNmNode            = $this->xml->createElement('SchmeNm');
        $prtryNode              = $this->xml->createElement('Prtry');
        
        //Fill in the blanks
        $pmtInfIdNode->nodeValue     = $this->makeId();
        $pmtMtdNode->nodeValue       = 'DD'; //Direct Debit
        $btchBookgNode->nodeValue    = 'true';
        $ctrlSumNode->nodeValue      = '0';
        $cdSvcLvlNode->nodeValue     = 'SEPA';
        $cdLclInstrmNode->nodeValue  = 'CORE';
        $seqTpNode->nodeValue        = $type; //Define a check for: FRST RCUR OOFF FNAL
        $reqdColltnDtNode->nodeValue = $date;
        
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $nmCdtrNode->nodeValue = htmlentities($this->config['name'], ENT_XML1, 'UTF-8');
        } else {
            $nmCdtrNode->nodeValue = htmlentities($this->config['name'], ENT_QUOTES, 'UTF-8');
        }
        
        $iBanCdtrAcctNode->nodeValue  = $this->config['IBAN'];
        if (isset($this->config['BIC'])) {
            $bicCdtrAgtNode->nodeValue    = $this->config['BIC'];
        } else {
            $idOthrCdtrAgtNode->nodeValue = 'NOTPROVIDED';
        }
        $chrgBrNode->nodeValue = 'SLEV';
        
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $nmCdtrSchmeIdNode->nodeValue = htmlentities($this->config['name'], ENT_XML1, 'UTF-8');
        } else {
            $nmCdtrSchmeIdNode->nodeValue = htmlentities($this->config['name'], ENT_QUOTES, 'UTF-8');
        }
        
        $idOthrNode->nodeValue = $this->config['creditor_id'];
        $prtryNode->nodeValue  = 'SEPA';
        
        //Fold the batch information
        $pmtInfNode->appendChild($pmtInfIdNode);
        $pmtInfNode->appendChild($pmtMtdNode);
        $pmtInfNode->appendChild($btchBookgNode);
        $pmtInfNode->appendChild($nbOfTxsNode);
        $pmtInfNode->appendChild($ctrlSumNode);
        
        $svcLvlNode->appendChild($cdSvcLvlNode);
        $pmtTpInfNode->appendChild($svcLvlNode);
        $lclInstrmNode->appendChild($cdLclInstrmNode);
        $pmtTpInfNode->appendChild($lclInstrmNode);
        $pmtTpInfNode->appendChild($seqTpNode);
        $pmtInfNode->appendChild($pmtTpInfNode);
        $pmtInfNode->appendChild($reqdColltnDtNode);
        
        $cdtrNode->appendChild($nmCdtrNode);
        $pmtInfNode->appendChild($cdtrNode);
        
        $idCdtrAcctNode->appendChild($iBanCdtrAcctNode);
        $cdtrAcctNode->appendChild($idCdtrAcctNode);
        $pmtInfNode->appendChild($cdtrAcctNode);
        
        if (isset($this->config['BIC'])) {
            $finInstnIdCdtrAgtNode->appendChild($bicCdtrAgtNode);
        } else {
            $othrCdtrAgtNode->appendChild($idOthrCdtrAgtNode);
            $finInstnIdCdtrAgtNode->appendChild($othrCdtrAgtNode);
        }
        $cdtrAgtNode->appendChild($finInstnIdCdtrAgtNode);
        $pmtInfNode->appendChild($cdtrAgtNode);
        
        $pmtInfNode->appendChild($chrgBrNode);
        
        $cdtrSchmeIdNode->appendChild($nmCdtrSchmeIdNode);
        $othrNode->appendChild($idOthrNode);
        $schmeNmNode->appendChild($prtryNode);
        $othrNode->appendChild($schmeNmNode);
        $prvtIdNode->appendChild($othrNode);
        $idCdtrSchmeIdNode->appendChild($prvtIdNode);
        $cdtrSchmeIdNode->appendChild($idCdtrSchmeIdNode);
        $pmtInfNode->appendChild($cdtrSchmeIdNode);
        
        //Add it to the batchArray.
        $this->batchArray[$type . '::' . $date]['node'] = $pmtInfNode;
        $this->batchArray[$type . '::' . $date]['ctrlSum'] = $ctrlSumNode;
        $this->batchArray[$type . '::' . $date]['nbOfTxs'] = $nbOfTxsNode;
        $this->batchArray[$type . '::' . $date]['pmtInfId'] = $pmtInfIdNode;
        
        //Return the batch array for this type and date.
        return $this->batchArray[$type . '::' . $date];
    }
    
    /**
     * Check, if batch array is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->batchArray);
    }
    
    /**
     * Get direct debit info
     *
     * @return array
     */
    public function getDirectDebitInfo()
    {
        $info = array();
        $info['MessageId'] = $this->xml->getElementsByTagName('MsgId')->item(0)->nodeValue;
        $info['TotalTransactions'] = 0;
        $info['TotalAmount'] = 0;
        $info['FirstCollectionDate'] = null;
        if ($this->config['batch']) {
            $batches = array();
            foreach ($this->batchArray as $key => $batch) {
                $batchInfo = array();
                $batchKey = explode('::', $key);
                $date = $batchKey[1];
                $batchInfo['CollectionDate'] = $date;
                
                $dateObject = \DateTime::createFromFormat('Y-m-d', $date);
                if ($info['FirstCollectionDate'] == null || $dateObject > $info['FirstCollectionDate']) {
                    $info['FirstCollectionDate'] = $dateObject;
                }
                
                $batchInfo['Type'] = $batchKey[0];
                $batchInfo['BatchId'] = $batch['pmtInfId']->nodeValue;
                $txs = (int)$batch['nbOfTxs']->nodeValue;
                $batchInfo['BatchTransactions'] = $txs;
                $info['TotalTransactions'] += $txs;
                $amount = (int)$this->decimalToInt($batch['ctrlSum']->nodeValue);
                $batchInfo['BatchAmount'] = (string)$amount;
                $info['TotalAmount'] += $amount;
                
                $batches[] = $batchInfo;
            }
            $info['Batches'] = $batches;
        } else {
            $trxCount = $this->xml->getElementsByTagName('DrctDbtTxInf');
            $info['TotalTransactions'] = $trxCount->length;
            $trxAmounts = $this->xml->getElementsByTagName('InstdAmt');
            $trxAmountArray = array();
            foreach ($trxAmounts as $amount) {
                $trxAmountArray[] = $amount->nodeValue;
            }
            $info['TotalAmount'] = $this->calcTotalAmount($trxAmountArray);
            $dates = $this->xml->getElementsByTagName('ReqdColltnDt');
            $datesCount = $dates->length;
            for($idx = 0; $idx < $datesCount; $idx++) {
                $dateObject = \DateTime::createFromFormat('Y-m-d', $dates->item($idx)->nodeValue);
                if ($info['FirstCollectionDate'] == null || $dateObject > $info['FirstCollectionDate']) {
                    $info['FirstCollectionDate'] = $dateObject;
                }
            }
        }
        $info['TotalAmount'] = (string)$this->decimalToInt($info['TotalAmount']);
        if ($info['FirstCollectionDate'] instanceof \DateTime) {
            $info['FirstCollectionDate'] = $info['FirstCollectionDate']->format('Y-m-d');
        }
        return $info;
    }
}
