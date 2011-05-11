<?php
/**
 * PayPal Express Auto Convert
 * @class PayPalExpressAutoConvert
 *
 * @author Mark Theunissen
 * @version 1.0
 * @copyright GPL V2.0
 * @package shopp
 * @since 1.1
 * @subpackage PayPalExpressAutoConvert
 *
 * $Id$
 **/

// If the original PayPal Express payment option is not enabled, we need 
// to include the code.
include_once('PayPalExpress.php');

class PayPalExpressAutoConvert extends PayPalExpress implements GatewayModule {

	// Override the purchase function so that we can automatically convert up
	// from ZAR to USD.
	function purchase () {
        global $Shopp;
		$_ = array();
		$fx = new ForeignExchange('ZAR', 'USD');

		// Transaction
		$_['CURRENCYCODE']			= 'USD'; // $this->settings['currency_code'];
		
		$amt = $fx->toForeign($this->Order->Cart->Totals->total);
		$_['AMT']					= number_format($amt,$this->precision);
		
		$item_amt = $this->Order->Cart->Totals->subtotal - $this->Order->Cart->Totals->discount;
		$item_amt = $fx->toForeign($item_amt);
		$_['ITEMAMT']				= number_format($item_amt,$this->precision);
		
		$shipping_amt = $fx->toForeign($this->Order->Cart->Totals->shipping);
		$_['SHIPPINGAMT']			= number_format($shipping_amt,$this->precision);
		
		$tax_amt = $fx->toForeign($this->Order->Cart->Totals->tax);
		$_['TAXAMT']				= number_format($tax_amt,$this->precision);

		$_['EMAIL']					= $this->Order->Customer->email;
		$_['PHONENUM']				= $this->Order->Customer->phone;

		// Shipping address override
		if (!empty($this->Order->Shipping->address) && !empty($this->Order->Shipping->postcode)) {
			$_['ADDRESSOVERRIDE'] = 1;
			$_['SHIPTOSTREET'] 		= $this->Order->Shipping->address;
			if (!empty($this->Order->Shipping->xaddress))
				$_['SHIPTOSTREET2']	= $this->Order->Shipping->xaddress;
			$_['SHIPTOCITY']		= $this->Order->Shipping->city;
			$_['SHIPTOSTATE']		= $this->Order->Shipping->state;
			$_['SHIPTOZIP']			= $this->Order->Shipping->postcode;
			$_['SHIPTOCOUNTRY']		= $this->Order->Shipping->country;
		}

		if (empty($this->Order->Cart->shipped) &&
			!in_array($this->settings['locale'],$this->shiprequired)) $_['NOSHIPPING'] = 1;

		// Line Items
		foreach($this->Order->Cart->contents as $i => $Item) {
			$_['L_NUMBER'.$i]		= $i;
			$_['L_NAME'.$i]			= $Item->name.((!empty($Item->option->label))?' '.$Item->option->label:'');
			
			$l_amt = $fx->toForeign($Item->unitprice);
			$_['L_AMT'.$i]			= number_format($l_amt,$this->precision);
			
			$_['L_QTY'.$i]			= $Item->quantity;
			
			$l_tax_amt = $fx->toForeign($Item->taxes);
			$_['L_TAXAMT'.$i]		= number_format($Item->taxes,$this->precision);
		}

		if ($this->Order->Cart->Totals->discount != 0) {
			$discounts = array();
			foreach($this->Order->Cart->discounts as $discount)
				$discounts[] = $discount->name;
			
			$i++;
			$_['L_NUMBER'.$i]		= $i;
			$_['L_NAME'.$i]			= join(", ",$discounts);
			$l_amt = $fx->toForeign($this->Order->Cart->Totals->discount * -1);
			$_['L_AMT'.$i]			= number_format($l_amt,$this->precision);
			$_['L_QTY'.$i]			= 1;
			$_['L_TAXAMT'.$i]		= number_format(0,$this->precision);
		}

		return $_;
	}
	

} // END class PayPalExpressAutoConvert

// PHP Code to convert currencies using Yahoo's currency conversion service.
// by Mark Theunissen, borrowed heavily from Adam Pierce <adam@doctort.org> 
class ForeignExchange
{
	public $fxRate;

	function __construct($currencyBase, $currencyForeign)
	{
		$url = 'http://download.finance.yahoo.com/d/quotes.csv?s='
			.$currencyBase .$currencyForeign .'=X&f=l1';

		$c = curl_init($url);
		curl_setopt($c, CURLOPT_HEADER, 0);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($c);
		// Result of HTTP query is a string, otherwise fail.
		if (!is_string($res)) {
		  // Made up error code.
		  $message = 'The server found an invalid exchange rate. Please try again later.';
		  new ShoppError($message,'paypal_express_transacton_error',SHOPP_TRXN_ERR,array('codes'=>'1000000'));
		  shopp_redirect(shoppurl(false,'checkout'));
		}
		$this->fxRate = doubleval($res);
		// The rate must be between 0 and 1, and not exactly either, otherwise
		// something is wrong.
		if ($this->fxRate >= 1 || $this->fxRate <= 0) {
		  // Made up error code.
		  $message = 'The server found an invalid exchange rate. Please try again later.';
		  new ShoppError($message,'paypal_express_transacton_error',SHOPP_TRXN_ERR,array('codes'=>'1000000'));
		  shopp_redirect(shoppurl(false,'checkout'));
		}
		curl_close($c);
	}

	public function toBase($amount)
	{
		return  $amount / $this->fxRate;
	}

	public function toForeign($amount)
	{
		return $amount * $this->fxRate;
	}
}


?>