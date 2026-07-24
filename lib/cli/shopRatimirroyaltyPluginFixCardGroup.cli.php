<?php
class shopRatimirroyaltyPluginFixCardGroupCli extends waCliController
{
    public function execute() {
        $model = new shopRatimirroyaltyPluginModel();
        $contacts = $model->getAll();
        
        $localCardModel = new shopRatimirroyaltyPluginLocalTableDiscountCardsModel();
        $c2cgModel = new shopRatimirroyaltyPluginLocalTableDc2dcgModel();

        foreach ($contacts as $contact) {
            $contact_id = $contact['contact_id'];
            $cardInfo = $localCardModel->getByField('Barcode', $contact['card']);
            $cardId = $cardInfo['DiscountCardID'];
            $cardGroupInfo = $c2cgModel->getByField('DiscountCardID', $cardId);
            $cardGroupId = $cardGroupInfo['DiscountCardGroupID'];

            $updatedCheck = $model->updateByField('contact_id', (int)$contact_id, [
                'DiscountCardID' => (int)$cardId,
                'DiscountCardGroupID' => (int)$cardGroupId
            ]);
        }

    }
}