<?php
class shopRatimirroyaltyPluginSyncBonusesCli extends waCliController
{
    public function execute()
    {
        $this->updateBonusTable();
        $model = new shopRatimirroyaltyPluginLocalTableIndicatorsModel();

        // Шаг 1: Получение данных из таблицы royalty_indocators
        $sql = "SELECT AccountId, SUM(Bonus) as Bonus
        FROM royalty_indicators
        GROUP BY AccountId";
        $result = $model->query($sql)->fetchAll();

        // Шаг 2: Получение данных из таблицы royalty_ratimir
        $userTable = $model->query("SELECT * FROM royalty_ratimir")->fetchAll();
        $existDiscountCard = [];
        foreach ($userTable as $t) {
            $existDiscountCard[$t['contact_id']] = $t['card'];
        }
        
        // Шаг 3: Получение данных из таблицы royalty_discount_cards и сопоставление с AccountId
        $accountIds = array_column($result, 'AccountId');
        $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
        $sql = "SELECT Barcode, AccountID FROM royalty_discount_cards WHERE AccountID IN ($placeholders)";
        $discountCards = $model->query($sql, $accountIds)->fetchAll();

        // Шаг 4: Создание массива с contact_id = AccountID
        $contactIdToAccountId = [];
        foreach ($discountCards as $card) {
            foreach ($existDiscountCard as $contactId => $barcode) {
                if ($barcode == $card['Barcode']) {
                    $contactIdToAccountId[$contactId] = $card['AccountID'];
                }
            }
        }
        $bonusesToUpdate = [];

        foreach ($result as $row) {
            $accountId = $row['AccountId'];
            $bonus = $row['Bonus'];

            // Найти contact_id, соответствующий данному AccountId
            $contactId = array_search($accountId, $contactIdToAccountId);

            if ($contactId !== false) {
                $bonusesToUpdate[$contactId] = $bonus;
            }
        }
        // wa_dumpc($bonusesToUpdate);
        
        foreach ($bonusesToUpdate as $contact_id => $bonus) {
            
            if ($contact_id == 178) {
                                
            }
            // wa_dumpc($contact_id);
            // wa_dumpc($bonus);
            $model->query("UPDATE `shop_customer` SET `affiliate_bonus` = " . $bonus . " WHERE `contact_id` = " . $contact_id);
        }



    }
    private $batchSize = 500;  // Размер пакета для вставки

    public function updateBonusTable()
    {
        $csvFiles = [
            'Indicators' => '/home/u541789/shop.ratimir.ru/royalty_base/tables/Indicators.csv',
        ];
        $tables = [
            'Indicators' => 'royalty_indicators',
        ];
        $models = [
            'Indicators' => new shopRatimirroyaltyPluginLocalTableIndicatorsModel(),
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
        }

        return [];
    }

    // Вставляем данные пакетами
    private function insertBatch($model, $batchData)
    {
        $model->multipleInsert($batchData);
    }
}