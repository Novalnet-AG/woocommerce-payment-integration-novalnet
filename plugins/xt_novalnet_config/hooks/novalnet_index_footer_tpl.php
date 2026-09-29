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

if (isset($_GET['page_action']) && $_GET['page_action'] == 'pay' && strpos($_SESSION['selected_payment'], 'novalnet') !== false) { ?>
    <script>
        jQuery('#content input[type=submit]').click(function() {
            setTimeout(function() {
                jQuery('#content input[type=submit]').attr(
                    'disabled', 'disabled'
                ).css(
                    'opacity', '0.2'
                );
            }, 1000);
        });
    </script>
<?php }
