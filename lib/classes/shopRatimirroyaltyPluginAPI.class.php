<?php
class shopRatimirroyaltyPluginAPI {
    public function xmlToArray($node) {
        $output = [];
    
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = $this->xmlToArray($child);
                    if (isset($child->tagName)) {
                        $t = $child->tagName;
                        if (!isset($output[$t])) {
                            $output[$t] = [];
                        }
                        $output[$t][] = $v;
                    } elseif ($v || $v === '0') {
                        $output = (string) $v;
                    }
                }
                if ($node->attributes->length && !is_array($output)) { // Has attributes but isn't an array
                    $output = ['@content' => $output]; // Change output into an array.
                }
                if (is_array($output)) {
                    if ($node->attributes->length) {
                        $a = [];
                        foreach ($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string) $attrNode->value;
                        }
                        $output['@attributes'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) === 1 && $t !== '@attributes') {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
    
        return $output;
    }
    
    public function initializeSoapClient($wsdl, $options = []) {
        try {
            $client = new SoapClient($wsdl, $options);
            return $client;
        } catch (Exception $e) {
            waLog::dump('Ошибка инициализации SOAP-клиента: ' . $e->getMessage(), 'royalty_error.log');
            return null;
        }
    }
    public function executeSoapCall($client, $functionName, $params) {
        try {
            $params = new SoapVar($params, SOAP_ENC_OBJECT, null, null, $functionName);
            waLog::dump($client, 'royalty.log');
            $client->__soapCall($functionName, [$params]);
            $xmlResponse = $client->__getLastResponse();
            waLog::dump($xmlResponse, 'royalty.log');
            $dom = new DOMDocument();
            $dom->loadXML($xmlResponse);
            $root = $dom->documentElement;
            $arrayResponse = $this->xmlToArray($root);
            return $arrayResponse;
        } catch (Exception $e) {
            echo 'Ошибка выполнения SOAP-вызова: ' . $e->getMessage();
            waLog::dump($e->getMessage(), 'royalty.log');
            return null;
        }
    }
    public function tokenParams($contact_id) {
        $contact = new waContact($contact_id);
        $phone = $contact->get('phone', 'value');
        if (empty($phone) || empty($phone[0])) {
            waLog::log('no-phone for user id = '.$contact_id, 'royalty_error.log');
            return null;
        }
        $phone = preg_replace('/[^\d+]/', '', $phone[0]);
        $tokenParams = [
            'authToken' => $phone,
            'password' => "ardoz",
            'type' => 'Phone',
        ];
        return $tokenParams;
    }
}