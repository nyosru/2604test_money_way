<?php

class SyncCommand

{
    /**
     * @param $invoiceName
     * @param $paymentPurpose
     * @return bool
     */
    protected static function invoiceNumberInPurpose($invoiceName, $paymentPurpose)
    {
        $prepareStr = preg_replace('/\D/', ' ', $paymentPurpose);
        $prepareStr = preg_replace('/\s+/', ' ', $prepareStr);

        $ppAr = explode(' ', $prepareStr);
        foreach ($ppAr as $piece) {
            if ($piece == $invoiceName) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param ah          $paymentsIn
     * @param MoyskladApp $msApp
     *
     * @return void
     * @throws Exception
     */
    protected function attachToInvoiceOut(ah $paymentsIn, MoyskladApp $msApp)
    {
        $attributes = $this->user->get('settings.' . AttributeModel::TABLE_NAME, new ah());
        $isAttachedToInvoiceAttr = $attributes->get('paymentin.isAttachedToInvoice')->getAll();

        $msApi = $msApp->getJsonApi();
        $invoicesOut = $msApi->getEntityRows('invoiceout', [
            'expand' => 'organizationAccount, agent'
        ]);

        $updatePayment = [];
        $updateInvoiceOut = [];
        // счета что оплачены
        $InvoiceOutPayed = [];

        $paymentsIn->each(function($payment) use (
            $invoicesOut,
            &$updatePayment,
            &$updateInvoiceOut,
            &$isAttachedToInvoiceAttr,
            &$InvoiceOutPayed
        ) {

            foreach ($invoicesOut as &$invoiceOut) {

                $arr = new ah($invoiceOut);
                if (empty($arr['organizationAccount']['meta']['href'])) {
                    continue;
                }

                // если счёт уже был оплачен ранее в этой функции, то пропускаем
                if( isset($InvoiceOutPayed[$arr['name']]) ){
                    continue;
                }

                $notEqualAgent = !TextHelper::isEqual($arr['agent']['meta']['href'], $payment['agent']['meta']['href']);
                $notEqualAccount = !TextHelper::isEqual($arr['organizationAccount']['meta']['href'], $payment['organizationAccount']['meta']['href']);
                $notEqualOrganization = !TextHelper::isEqual($arr['organization']['meta']['href'], $payment['organization']['meta']['href']);
                if ($notEqualAgent || $notEqualAccount || $notEqualOrganization) {
                    continue;
                }

                // найти номер счета в назначении платежа
                $attachedByPurpose = false;
                if (strpos($payment['paymentPurpose'], $arr['name']) !== false
                    || ((int)$arr['name'] !== 0 && strpos($payment['paymentPurpose'], (string)(int)$arr['name']) !== false)) {
                    $attachedByPurpose = self::invoiceNumberInPurpose($arr['name'], $payment['paymentPurpose']);
                }

                // найти дату выставления счета в назначении платежа
                if (!$attachedByPurpose && $arr['sum'] == $payment['sum']) {
                    $prepareDate = date('d.m.Y', strtotime($arr['moment']));
                    $attachedByPurpose = strpos($payment['paymentPurpose'], $prepareDate) !== false;
                }

                // Не привязываем платёж только по совпадению реквизитов и суммы:
                // в назначении должен подтвердиться номер счёта или дата выставления.
                if (!$attachedByPurpose) {
                    continue;
                }

                $isAttachedToInvoiceAttr['value'] = true;
                $payment['attributes'] = [$isAttachedToInvoiceAttr];
                $payment['operations'] = [['meta' => $invoiceOut['meta']]];
                $updatePayment[] = $payment;

                $invoiceOut['payments'] = [['meta' => $payment['meta']]];
                $updateInvoiceOut[] = $invoiceOut;

                // отметка оплаты исходящего счёта
                $InvoiceOutPayed[$arr['name']] = true;

                return;
            }
        });

        if (!empty($updatePayment)) {
            $msApi->sendEntity('paymentin', $updatePayment);
        }

        if (!empty($updateInvoiceOut)) {
            $msApi->sendEntity('invoiceout', $updateInvoiceOut);
        }
    }

    /** Дальшейшие методы класса */

}
