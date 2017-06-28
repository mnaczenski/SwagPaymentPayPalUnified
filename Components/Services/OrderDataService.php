<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace SwagPaymentPayPalUnified\Components\Services;

use Doctrine\DBAL\Connection;
use SwagPaymentPayPalUnified\PayPalBundle\Components\SettingsServiceInterface;
use SwagPaymentPayPalUnified\PayPalBundle\PaymentType;
use SwagPaymentPayPalUnified\PayPalBundle\Structs\Payment;

class OrderDataService
{
    /**
     * @var Connection
     */
    private $dbalConnection;

    /**
     * @var SettingsServiceInterface
     */
    private $settingsService;

    /**
     * @param Connection               $dbalConnection
     * @param SettingsServiceInterface $settingsService
     */
    public function __construct(
        Connection $dbalConnection,
        SettingsServiceInterface $settingsService
    ) {
        $this->dbalConnection = $dbalConnection;
        $this->settingsService = $settingsService;
    }

    /**
     * @param string $orderNumber
     * @param int    $paymentStatusId
     *
     * @return bool
     */
    public function applyPaymentStatus($orderNumber, $paymentStatusId)
    {
        $builder = $this->dbalConnection->createQueryBuilder();
        $result = $builder->update('s_order', 'o')
            ->set('o.cleared', ':paymentStatus')
            ->where('o.ordernumber = :orderNumber')
            ->setParameters([
                ':orderNumber' => $orderNumber,
                ':paymentStatus' => $paymentStatusId,
            ])
            ->execute();

        return $result === 1;
    }

    /**
     * @param int    $orderNumber
     * @param string $transactionId
     *
     * @return bool
     */
    public function applyTransactionId($orderNumber, $transactionId)
    {
        $builder = $this->dbalConnection->createQueryBuilder();
        $result = $builder->update('s_order', 'o')
            ->set('o.transactionID', ':transactionId')
            ->where('o.ordernumber = :orderNumber')
            ->setParameters([
                ':orderNumber' => $orderNumber,
                ':transactionId' => $transactionId,
            ])
            ->execute();

        return $result === 1;
    }

    /**
     * @param int     $orderNumber
     * @param Payment $payment
     * @param bool    $expressCheckout
     *
     * @see PaymentType
     */
    public function applyPaymentTypeAttribute($orderNumber, $payment, $expressCheckout = false)
    {
        $paymentType = PaymentType::PAYPAL_CLASSIC;
        $payer = $payment->getPayer();

        if ($expressCheckout) {
            $paymentType = PaymentType::PAYPAL_EXPRESS;
        } elseif ($payer && $payment->getPayer()->getExternalSelectedFundingInstrumentType() === 'CREDIT') {
            $paymentType = PaymentType::PAYPAL_INSTALLMENTS;
        } elseif ($payment->getPaymentInstruction() !== null) {
            $paymentType = PaymentType::PAYPAL_INVOICE;
        } elseif ((bool) $this->settingsService->get('plus_active')) {
            $paymentType = PaymentType::PAYPAL_PLUS;
        }

        $builder = $this->dbalConnection->createQueryBuilder();

        //Since joins are being stripped out, we have to select the correct orderId by a sub query.
        $subQuery = $this->dbalConnection->createQueryBuilder()
            ->select('o.id')
            ->from('s_order', 'o')
            ->where('o.ordernumber = :orderNumber')
            ->getSQL();

        $builder->update('s_order_attributes', 'oa')
            ->set('oa.swag_paypal_unified_payment_type', ':paymentType')
            ->where('oa.orderID = (' . $subQuery . ')')
            ->setParameters([
                ':orderNumber' => $orderNumber,
                ':paymentType' => $paymentType,
            ])->execute();
    }

    /**
     * @param int $orderNumber
     *
     * @return string
     */
    public function getTransactionId($orderNumber)
    {
        $builder = $this->dbalConnection->createQueryBuilder();
        $builder->select('o.transactionId')
            ->from('s_order', 'o')
            ->where('o.ordernumber = :orderNumber')
            ->setParameter(':orderNumber', $orderNumber);

        return $builder->execute()->fetchColumn();
    }
}
