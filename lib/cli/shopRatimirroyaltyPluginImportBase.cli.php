<?php
class shopRatimirroyaltyPluginImportBaseCli extends waCliController
{
    private $batchSize = 500;  // Размер пакета для вставки

    public function execute()
    {
        $csvFiles = [
            'Accounts' => '/home/u541789/shop.ratimir.ru/royalty_base/tables/Accounts.csv',
            'CustomerPhones' => '/home/u541789/shop.ratimir.ru/royalty_base/tables/CustomerPhones.csv',
            'Customers' => '/home/u541789/shop.ratimir.ru/royalty_base/tables/Customers.csv',
            'DiscountCards' => '/home/u541789/shop.ratimir.ru/royalty_base/tables/DiscountCards.csv',
            'Indicators' => '/home/u541789/shop.ratimir.ru/royalty_base/tables/Indicators.csv',
            'Customerpropertyvalues' => '/home/u541789/shop.ratimir.ru/royalty_base/tables/CustomerPropertyValues.csv',
            'Discountcard2discountcardgroup' => '/home/u541789/shop.ratimir.ru/royalty_base/tables/DiscountCard2DiscountCardGroup.csv',
        ];
        $tables = [
            'Accounts' => 'royalty_accounts',
            'CustomerPhones' => 'royalty_customerphones',
            'Customers' => 'royalty_customers',
            'DiscountCards' => 'royalty_discount_cards',
            'Indicators' => 'royalty_indicators',
            'Customerpropertyvalues' => 'royalty_customerpropertyvalues',
            'Discountcard2discountcardgroup' => 'royalty_discountcard2discountcardgroup',
        ];
        $models = [
            'Accounts' => new shopRatimirroyaltyPluginLocalTableAccountsModel(),
            'CustomerPhones' => new shopRatimirroyaltyPluginLocalTableCustomerPhonesModel(),
            'Customers' => new shopRatimirroyaltyPluginLocalTableCustomersModel(),
            'DiscountCards' => new shopRatimirroyaltyPluginLocalTableDiscountCardsModel(),
            'Indicators' => new shopRatimirroyaltyPluginLocalTableIndicatorsModel(),
            'Discountcard2discountcardgroup' => new shopRatimirroyaltyPluginLocalTableDc2dcgModel(),
            'Customerpropertyvalues' => new shopRatimirroyaltyPluginLocalTableCustomerpropertyvaluesModel(),
        ];
        foreach ($models as $model_id => $model) {
            
            $model->truncateTable();
        }
        foreach ($csvFiles as $table => $filePath) {
            waLog::dump('Start import table ', $table, 'royalty/base_import.log');
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                $batchData = [];
                while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    if ($this->isHeaderRow($table, $data)) {
                        continue;  // Пропуск заголовка
                    }

                    $batchData[] = $this->prepareData($table, $data);

                    // Когда накопится 1000 записей, выполняем вставку
                    if (count($batchData) >= $this->batchSize) {
                        $this->insertBatch($models[$table], $batchData);
                        $batchData = [];  // Очистка массива для следующего пакета
                    }
                }

                // Вставляем оставшиеся записи, если они есть
                if (!empty($batchData)) {
                    $this->insertBatch($models[$table], $batchData);
                }

                fclose($handle);
            }
            waLog::dump('End import table ', $table, 'royalty/base_import.log');
        }
        /*foreach ($models as $model) {
            $model->renameTable();
        }*/
    }

    // Проверяем, является ли строка заголовком
    private function isHeaderRow($table, $data)
    {
        switch ($table) {
            case 'Accounts':
                return $data[0] == 'AccountID';
            case 'CustomerPhones':
                return $data[0] == 'PropertyID';
            case 'Customers':
                return $data[0] == 'CustomerID';
            case 'DiscountCards':
                return $data[0] == 'DiscountCardID';
            case 'Indicators':
                return $data[0] == 'IndicatorID';
            case 'Customerpropertyvalues':
                return $data[0] == 'CustomerPropertyID';
            case 'Discountcard2discountcardgroup': 
                return $data[0] == 'DiscountCardID';
            default:
                return false;
        }
    }

    // Подготовка данных для каждой таблицы
    private function prepareData($table, $data)
    {
        switch ($table) {
            case 'Discountcard2discountcardgroup': 
                return [
                    'DiscountCardID' => $data[0],
                    'DiscountCardGroupID' => $data[1],
                    'State' => $data[2]
                ];
            case 'Accounts':

                return [
                    'AccountID' => $data[0],
                    'CustomerID' => $data[1],
                    'State' => hex2bin(trim($data[2], '0x')),
                    'BeginBlockingDateTime' => $data[3] ?: null,
                    'EndBlockingDateTime' => $data[4] ?: null,
                    'BlockReason' => $data[5]
                ];

            case 'CustomerPhones':
                return [
                    'PropertyID' => $data[0],
                    'CustomerID' => $data[1],
                    'Phone' => $data[2]
                ];

            case 'Customers':
                return [
                    'CustomerID' => $data[0],
                    'FullNameObsolete' => $data[1],
                    'RegistrationDate' => $data[2] ?: null,
                    'Login' => $data[3],
                    'SecretCode' => $data[4],
                    'IsActivated' => (int) 1,
                    'IsDeleted' => $data[6],
                    'IsSubscribed' => $data[7],
                    'Token' => $data[8],
                    'IsSmsSubscribed' => $data[9],
                    'IsFillAllProperties' => $data[10],
                    'IsAddedBonusForRegistration' => $data[11],
                    'CustomerUID' => $data[12],
                    'State' => hex2bin(trim($data[13], '0x')) ?: null,
                    'FirstName' => $data[14],
                    'SecondName' => $data[15],
                    'LastName' => $data[16],
                    'AuthorizationToken' => $data[17],
                    'MobileActivationDate' => $data[18] ?: null,
                    'CreatedDate' => $data[19] ?: null,
                    'CreatedUserID' => $data[20],
                    'ModifiedDate' => $data[21] ?: null,
                    'ModifiedUserID' => $data[22],
                    'PhoneIsChecked' => $data[23],
                    'IsAddedBonusForMobileRegistration' => $data[24],
                    'AdministrativeAreaID' => $data[25],
                    'LocalityID' => $data[26],
                    'EmailIsChecked' => $data[27],
                    'IsSmsActivated' => $data[28],
                    'ActivationDate' => $data[29] ?: null,
                    'ActivatedTypeId' => $data[30]
                ];

            case 'DiscountCards':
                return [
                    'DiscountCardID' => $data[0],
                    'Number' => $data[1],
                    'IsActive' => (int) 1,
                    'IsBlocked' => $data[3],
                    'ExpirationDate' => $data[4] ?: null,
                    'AccountID' => $data[5],
                    'IsDeleted' => $data[6],
                    'StoreID' => $data[7],
                    'ActivationDate' => $data[8] ?: null,
                    'DiscountCardUID' => $data[9],
                    'State' => hex2bin(trim($data[10], '0x')),
                    'ReasonBlocking' => $data[11],
                    'CreatedDate' => $data[12] ?: null,
                    'CreatedUserID' => $data[13],
                    'ModifiedDate' => $data[14] ?: null,
                    'ModifiedUserID' => $data[15],
                    'IsVirtual' => $data[16],
                    'Barcode' => $data[17],
                    'PinCode' => $data[18],
                    'ReasonBlockType' => $data[19]
                ];

            case 'Indicators':
                return [
                    'IndicatorID' => $data[0],
                    'Amount' => $data[1],
                    'Bonus' => $data[2],
                    'Visits' => $data[3],
                    'LastCheckTransactionId' => $data[4],
                    'CompanyID' => $data[5],
                    'AccountID' => $data[6],
                    'LastProcessedDate' => $data[7] !== '' ? $data[7] : null,
                    'State' => hex2bin(trim($data[8], '0x'))
                ];

            case 'Customerpropertyvalues':
                
                return [
                    'CustomerPropertyID' => (int)$data[0], // Поле обязательно, не допускает NULL
                    'IntValue' => isset($data[1]) && $data[1] !== '' ? (int)$data[1] : null, // Преобразуем пустую строку в NULL
                    'StringValue' => isset($data[2]) && $data[2] !== '' ? $data[2] : null, // Преобразуем пустую строку в NULL
                    'DateValue' => isset($data[3]) && $data[3] !== '' ? $data[3] : null, // Преобразуем пустую строку в NULL
                    'BooleanValue' => isset($data[4]) && $data[4] !== '' ? (int)$data[4] : null, // Преобразуем пустую строку в NULL
                    'PropertyID' => (int)$data[5], // Поле обязательно, не допускает NULL
                    'EnumPropertyValueID' => isset($data[6]) && $data[6] !== '' ? (int)$data[6] : null, // Преобразуем пустую строку в NULL
                    'CustomerID' => isset($data[7]) && $data[7] !== '' ? (int)$data[7] : null, // Преобразуем пустую строку в NULL
                    'State' => hex2bin(trim($data[8], '0x')), // Преобразуем шестнадцатеричные данные в бинарные
                    'LoadDate' => isset($data[9]) && $data[9] !== '' ? $data[9] : null // Преобразуем пустую строку в NULL
                ];


        }

        return [];
    }

    // Вставляем данные пакетами
    private function insertBatch($model, $batchData)
    {
        $model->multipleInsert($batchData);
    }
}
