<?php

class shopRatimirroyaltyPlugin extends shopPlugin
{
    public static function createAPI($api) {
        $wsdl = "https://172.17.2.58/RS.Loyalty.WebClientPortal.Service/RSLoyaltyClientService.svc?wsdl";
        $options = [
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 30,
            'location' => 'https://172.17.2.58/RS.Loyalty.WebClientPortal.Service/RSLoyaltyClientService.svc?wsdl',
            'uri' => 'https://172.17.2.58/RS.Loyalty.WebClientPortal.Service/RSLoyaltyClientService.svc?wsdl',
            'cache_wsdl' => WSDL_CACHE_NONE,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ])
        ];
        $client = $api->initializeSoapClient($wsdl, $options);
        return $client;
    }
    public static function getDataFromAPI($contact_id) {
        $api = new shopRatimirroyaltyPluginAPI();
        $client = shopRatimirroyaltyPlugin::createAPI($api);
        // $tokenParams = [
        //     'authToken' => '+71234567890',
        //     'password' => "ardoz",
        //     'type' => 'Phone',
        // ];
        $tokenParams = $api->tokenParams($contact_id);
        if ($tokenParams === null) {
            return null;
        }
        $token = $api->executeSoapCall($client, 'GetTokenByType', $tokenParams);
        $token = $token["s:Body"]["GetTokenByTypeResponse"]["GetTokenByTypeResult"];
        $getCardsParams = [
            'token' => $token
        ];
        $cards = $api->executeSoapCall($client, 'GetDiscountCards', $getCardsParams);
        $cards = $cards['s:Body']['GetDiscountCardsResponse']['GetDiscountCardsResult']["a:DiscountCard"];

        $indicatorsParams = [
            'token' => $token
        ];
        $bonuses = $api->executeSoapCall($client, 'GetIndicators', $indicatorsParams);
        $bonuses = $bonuses["s:Body"]["GetIndicatorsResponse"]["GetIndicatorsResult"]["a:Indicator"];
        $bonus = 0;
        foreach ($bonuses as $bonuse) {
            if ($bonuse['a:Company']['a:CompanyID'] == 1) {
                $bonus = $bonuse["a:BonusAmount"];
            }
        }
        /*
        объект карты такой-же как и в запросе по токену
        $card_id = (string)$cards['a:DiscountCardID'];
        $cardParams = [
            'token' => $token,
            'discountCardId' => $card_id
        ];
        $cardObject = $api->executeSoapCall($client, 'GetDiscountCardById', $cardParams);
        
        wa_dumpc($cardObject);
        */
        $returnArray = [
            'cards' => $cards,
        ];
        $model = new shopRatimirroyaltyPluginModel();
        $model->updateAffiliate($contact_id, $bonus);
        $model->updateCardNumber($contact_id, $cards['a:Number']);
        return $returnArray;
    }
    public static function syncBonuses($contact_id) {
        $api = new shopRatimirroyaltyPluginAPI();
        $model = new waModel();
        $client = shopRatimirroyaltyPlugin::createAPI($api);
        // $tokenParams = [
        //     'authToken' => '+71234567890',
        //     'password' => "ardoz",
        //     'type' => 'Phone',
        // ];
        $tokenParams = $api->tokenParams($contact_id);
        $token = $api->executeSoapCall($client, 'GetTokenByType', $tokenParams);
        $token = $token["s:Body"]["GetTokenByTypeResponse"]["GetTokenByTypeResult"];
        $indicatorsParams = [
            'token' => $token
        ];
        $bonuses = $api->executeSoapCall($client, 'GetIndicators', $indicatorsParams);
        $bonuses = $bonuses["s:Body"]["GetIndicatorsResponse"]["GetIndicatorsResult"]["a:Indicator"];
        // wa_dumpc($bonuses);
        foreach ($bonuses as $bonuse) {
            if ($bonuse['a:Company']['a:CompanyID'] == 1) {
                $bonus = $bonuse["a:BonusAmount"];
            }
        }
        $model->query("UPDATE `shop_customer` SET `affiliate_bonus` = " . $bonus . " WHERE `contact_id` = " . $contact_id);
    }
    public static function getCardNumber($contact_id) {
        $model = new shopRatimirroyaltyPluginModel();
        $card_number = $model->getCardNumber($contact_id);
        return $card_number['card'];
    }
}
