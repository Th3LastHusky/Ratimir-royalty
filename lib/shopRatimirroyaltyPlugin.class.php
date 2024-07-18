<?php

class shopRatimirroyaltyPlugin extends shopPlugin
{
    public static function getDataFromAPI() {
        $api = new shopRatimirroyaltyPluginAPI();
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
        $tokenParams = [
            'authToken' => '+71234567890',
            'password' => "ardoz",
            'type' => 'Phone',
        ];
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
        $returnArray = [
            'cards' => $cards,
            'bonuses' => $bonuses,
        ];
        $card_id = (string)$cards['a:DiscountCardID'];
        $cardParams = [
            'token' => $token,
            'discountCardId' => $card_id
        ];
        $cardObject = $api->executeSoapCall($client, 'GetDiscountCardById', $cardParams);
        wa_dumpc($cardObject);
        return $returnArray;
    }
}
