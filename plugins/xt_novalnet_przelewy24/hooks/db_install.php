<?php
/**
 * Novalnet payment extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Novalnet End User License Agreement
 * that is bundled with this package in the file freeware_license_agreement.txt
 *
 * DISCLAIMER
 *
 * If you wish to customize Novalnet payment extension for your needs, please contact technic@novalnet.de for more information.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

defined('_VALID_CALL') or die('Direct Access is not allowed.');

/*
 * Inserting payment cost
 */
global $db;

$db->Execute(
	"INSERT INTO ".TABLE_PAYMENT_COST."
	(payment_id, payment_geo_zone, payment_country_code, payment_type_value_from, payment_type_value_to, payment_price, payment_allowed)
	VALUES
	(".$payment_id.", 24, '', 0, 10000.00, 0, 1),
	(".$payment_id.", 25, '', 0, 10000.00, 0, 1),
	(".$payment_id.", 26, '', 0, 10000.00, 0, 1),
	(".$payment_id.", 27, '', 0, 10000.00, 0, 1),
	(".$payment_id.", 28, '', 0, 10000.00, 0, 1),
	(".$payment_id.", 29, '', 0, 10000.00, 0, 1),
	(".$payment_id.", 30, '', 0, 10000.00, 0, 1),
	(".$payment_id.", 31, '', 0, 10000.00, 0, 1)"
);
