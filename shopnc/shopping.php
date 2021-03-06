<?php
/////////////////////////////////////////////////////////////////////////////
// 这个文件是 shopnc单用户 项目的一部分
//
// Copyright (c) 2007 - 2008 www.shopnc.net
//
// 要查看完整的版权信息和许可信息，请查看源代码中附带的 COPYRIGHT 文件，
// 或者访问 http://www.shopnc.net/ 获得详细信息。
/////////////////////////////////////////////////////////////////////////////

/**
* FILE_NAME : shopping.php D:\binzi\shopnc6\shopping.php
* 购物车极其购物流程
*
* @copyright Copyright (c) 2007 - 2007 www.shopnc.net 
* @author 网城创想单用户商城开发团队 php_netproject@yahoo.com.cn
* @package 
* @subpackage 
* @version Fri Sep 26 17:20:51 CST 2008
*/
require ("global.inc.php");
class ShowShopping extends CommonFrameWork {
	/**
	 * 购物车对象
	 *
	 * @var obj
	 */
	private $obj_shop_cart;

	/**
	 * 订单对象
	 *
	 * @var obj
	 */
	private $obj_shop_order;
	/**
	 * 产品对象
	 *
	 * @var obj
	 */
	private $obj_product;

	/**
	 * 支付对象
	 *
	 * @var obj
	 */
	private $obj_module_pay;

	/**
	 * 会员收货地址对象
	 */
	private $obj_user_other;


	function main(){
		/**
		 * 创建购物车对象
		 */
		if (!is_object($this->obj_shop_cart)) {
			require_once("shopCart.class.php");
			$this->obj_shop_cart = new ShopCartClass();
		}
		/**
		 * 创建产品对象
		 */
		if(!is_object($this->obj_product)) {
			require_once("product.class.php");
			$this->obj_product = new ProductClass();
		}
		/**
		 * 创建产品对象
		 */
		if(!is_object($this->obj_shop_order)) {
			require_once("shopOrder.class.php");
			$this->obj_shop_order = new ShopOrderClass();
		}
		/**
		 * 创建支付对象
		 */
		if(!is_object($this->obj_module_pay)) {
			require_once("modulePay.class.php");
			$this->obj_module_pay = new ModulePayClass();
		}
		/**
		 * 创建会员收货地址对象
		 */
		if (!is_object($this->obj_user_other)) {
			require_once("usersOther.class.php");
			$this->obj_user_other = new UsersOtherClass();
		}
		/**
		 * 语言包
		 */
		$this->getlang("index,shopping,header_footer,shopping_cart,shopping_cart_step1,shopping_cart_step2,shopping_cart_step3");

		/**
		 * 执行操作
		 */
		switch($this->_input['action']){
			case 'del_goods':	//删除购物车的商品
			$this->delCartGoods();
			break;
			case 'edit_num':	//编辑商品数量
			$this->editGoodsNum();
			break;
			case 'step_1':		//购买流程1
			$this->buyGoodsOne();
			break;
			case 'step_2':		//购买流程2
			$this->buyGoodsTwo();
			break;
			case 'step_3':		//购买流程3（保存订单）
			$this->buyGoodsThree();
			break;
			case 'shopnc_code':	//支付平台返回结果处理
			$this->modifyParment();
			break;
			default:
				$this->showCart();
		}

	}
	/**
	 * 购物车页面
	 *
	 */
	private function showCart() {
		/*得到商品信息*/
		if(intval($this->_input['goods_id']) != 0){
			$goods_array	= $this->obj_product->getProductInfo(array('goods_id'=>intval($this->_input['goods_id']),'no_state'=>1));
			if(intval($this->_input['goods_num']) != 0) {
				/*当购买的商品数不足时*/
				if($goods_array['goods_storage'] < intval($this->_input['goods_num'])) {
					$this->showMessage($this->_lang['shopping_product_num_error'],$this->refer_url,1,1000);
				}

				$goods_array['goods_pricedesc']	= $this->changeGoodsPrice($goods_array['goods_pricedesc']);
				$goods_array['goods_num']		= intval($this->_input['goods_num']);	//购买的商品数目
				$goods_array['select_option']	= $this->selectOption($goods_array['goods_num'],$goods_array['goods_storage']);//购物数量下拉菜单
			}
			$goods_array['color']	= trim($this->_input['goods_color']);
			$goods_array['size']	= trim($this->_input['goods_size']);

			$this->obj_shop_cart->addCartGoods($goods_array);
			$_SESSION['cart_goods'] = $this->obj_shop_cart->GetDate();
		}

		$this->output('cart_goods',$_SESSION['cart_goods']);
		/*商品金额*/
		$price_count	= $this->obj_shop_cart->goodsPriceCount();
		$this->output('price_count',$this->moneyFormat($price_count));
		/*折扣后的总金额*/
		$this->output('price_true_count',$this->goodsPriceDiscount($price_count));

		$this->showpage('cart01');
	}
	/**
	 * 删除商品
	 *
	 */	
	private function delCartGoods() {
		if($this->_input['type'] == 'clear_all') {
			$error	= $this->obj_shop_cart->DelCart();
		} else {
			$error	= $this->obj_shop_cart->DelOne(intval($this->_input['id']));
		}
		if($error) {
			$_SESSION['cart_goods'] = $this->obj_shop_cart->GetDate();
		}

		$this->redirectPath('common','shopping.php');
	}
	/**
	 * 修改购物车内的商品数量
	 *
	 */	
	private function editGoodsNum() {
		if(intval($this->_input['goods_num'])>=1) {
			/*购物车购物数量下拉菜单*/
			$select_option	= $this->selectOption(intval($this->_input['goods_num']),$_SESSION['cart_goods'][intval($this->_input['id'])]['goods_storage']);

			$error	= $this->obj_shop_cart->editGoods(intval($this->_input['id']),array('goods_num'=>intval($this->_input['goods_num']),'select_option'=>$select_option));
			$_SESSION['cart_goods'] = $this->obj_shop_cart->GetDate();
		}
		$this->redirectPath('common','shopping.php');
	}
	/**
	 * 购买流程1
	 *
	 */
	private function buyGoodsOne() {
		/*检查购物车*/
		$this->checkCart();
		/*对游客购买进行session赋值*/
		if(trim($this->_input['buy_member']) == 'guest') {
			$_SESSION['buy_member'] = 'guest';
		}
		/*判断是否游客购买*/
		if(empty($_SESSION['userinfo']['user_id']) and ($_SESSION['buy_member'] != 'guest' or empty($_SESSION['buy_member']))) {
			header("Location:./member/user.php?action=login_page&buy_member=guest");
			exit();
		}

		$this->output('cart_goods',$_SESSION['cart_goods']);
		/*商品总金额*/
		$price_count	= $this->obj_shop_cart->goodsPriceCount();
		$this->output('price_count',$this->moneyFormat($price_count));
		/*优惠后金额*/
		$this->output('price_true_count',$this->goodsPriceDiscount($price_count));
		/*商品总重量*/
		$goods_weight	= $this->obj_shop_cart->goodsWeightCount();
		$this->output('goods_weight',$goods_weight);
		/*收货地址*/
		if($_SESSION['userinfo']['user_id'] != '') {
			$address_array	= $this->obj_user_other->getUserList(array("user_uid"=>$_SESSION['userinfo']['user_id']));
		}
		$this->output('address_array',$address_array);
		/*支付方式*/
		$pay_type	= $this->obj_module_pay->getPayTypeList();
		$this->output('pay_type',$pay_type);

		/*动态的级联菜单，顶级菜单*/
		include('moduleRegion.class.php');
		$region	= new ModuleRegionClass();
		$top_region	= $region->regionList(array('area_top_id'=>0));
		$this->output('top_region',$top_region);

		$this->showpage('cart02');
	}
	/**
	 * 购买流程2
	 *
	 */
	private function buyGoodsTwo() {
		/*检查购物车*/
		$this->checkCart();

		/*判断配送方式id是否正确传递*/
		if(intval($this->_input['shipping']) == 0) {
			$this->showMessage($this->_lang['shopping_send_false'],$this->_configinfo['websit']['site_url']."/shopping.php?action=step_1",1,1000);
			return '';
		}
		/*判断支付方式id是否正确传递*/
		if(intval($this->_input['pay_id']) == 0) {
			$this->showMessage($this->_lang['shopping_pay_false'],$this->_configinfo['websit']['site_url']."/shopping.php?action=step_1",1,1000);
			return '';
		}

		$this->output('cart_goods',$_SESSION['cart_goods']);
		/*配送费用*/
		$this->output('nc_send_price',$this->moneyFormat($this->_input['nc_send_price']));
		/*商品总金额*/
		$price_count	= $this->obj_shop_cart->goodsPriceCount();
		$this->output('price_count',$this->moneyFormat($price_count));
		/*优惠后金额*/
		$this->output('price_true_count',$this->moneyFormat($this->goodsPriceDiscount($price_count)+intval($this->_input['nc_send_price'])+intval($this->_input['nc_pay_fee'])));
		/*配送方式*/
		include_once("moduleSend.class.php");
		$send_class = new ModuleSendClass();
		$this->output('send_info',$send_class->getSendInfo(array('send_id'=>intval($this->_input['shipping']))));
		/*收货人信息*/
		$user_info	= array();
		if(intval($this->_input['other_id']) != 0) {
			$user_info	= $this->obj_user_other->getUserOtherInfo(array('other_id'=>intval($this->_input['other_id'])));
		} else {
			$receiver_area	= array();
			/*载入地区管理类*/
			include_once("moduleRegion.class.php");
			$region_class = new ModuleRegionClass();

			for($i=0;$i<=3;$i++) {
				$area_id	= $this->_input['select'.$i];
				if(intval($area_id) != 0) {
					$other_area = $region_class->getAreaInfo(array('area_id'=>$area_id));
					$receiver_area['select'.$i]	= $other_area['area_name'];
					unset($other_area);
				}
			}

			$user_info['other_true_name']	= trim($this->_input['receiver_name']);		//收货人姓名
			$user_info['txt_other_country']	= trim($receiver_area['select0']);			//收货人国家
			$user_info['txt_other_province']= trim($receiver_area['select1']);			//收货人省
			$user_info['txt_other_city']	= trim($receiver_area['select2']);			//收货人城市
			$user_info['txt_other_county']	= trim($receiver_area['select3']);			//收货人县
			$user_info['other_address']		= trim($this->_input['receiver_address']);	//收货人具体地址
			$user_info['other_email']		= trim($this->_input['receiver_email']);	//收货人电子邮件
			$user_info['other_zip']			= trim($this->_input['receiver_zip']);		//收货人邮编
			$user_info['other_phone']		= trim($this->_input['receiver_call']);		//收货人电话
			$user_info['other_mobilephone']	= trim($this->_input['receiver_mobile']);	//收货人移动电话
		}
		$this->output('user_info',$user_info);

		/*支付方式*/
		$pay_type	= $this->obj_module_pay->getPayInfo(array('pay_id'=>$this->_input['pay_id']));
		$this->output('pay_type',$pay_type);

		$this->showpage('cart03');
	}
	/**
	 * 购物流程3（订单保存）
	 *
	 */	
	private function buyGoodsThree() {
		$input_param['receiver_name']		= $this->_input['receiver_name'];		//收货人姓名
		$input_param['receiver_country']	= $this->_input['receiver_country'];	//收货人国家
		$input_param['receiver_province']	= $this->_input['receiver_province'];	//收货人省
		$input_param['receiver_city']		= $this->_input['receiver_city'];		//收货人市
		$input_param['receiver_address']	= $this->_input['receiver_address'];	//收货人地址
		$input_param['receiver_email']		= $this->_input['receiver_email'];		//收货人email
		$input_param['receiver_post']		= $this->_input['receiver_post'];		//收货人邮编
		$input_param['receiver_tele']		= $this->_input['receiver_tele'];		//收货人电话
		$input_param['receiver_mobile']		= $this->_input['receiver_mobile'];		//收货人手机

		$input_param['transport_id']		= $this->_input['transport_id'];		//配送id
		$input_param['transport_name']		= $this->_input['transport_name'];		//配送名称
		$input_param['transport_price']		= $this->_input['transport_price'];		//配送价格
		$input_param['transport_content']	= $this->_input['transport_content'];	//配送内容

		$input_param['pay_id']				= $this->_input['pay_id'];				//支付id
		$input_param['pay_name']			= $this->_input['pay_name'];			//支付名称
		$input_param['pay_price']			= $this->moneyFormat($this->_input['pay_price']);//支付手续费用
		$input_param['pay_content']			= $this->_input['pay_content'];			//支付内容
		$input_param['price_count']			= $this->_input['price_count'];			//支付总金额

		$input_param['invoice']				= $this->_input['invoice'];				//是否需要发票
		$input_param['invoice_top']			= $this->_input['invoice_top'];			//发票抬头内容

		if(empty($_SESSION['cart_goods']) or empty($input_param['pay_id'])) {
			$this->showMessage($this->_lang['shopping_cart_null'],'index.php',1,1000);
			return '';
		}

		$order_serial	= $this->obj_shop_order->shopOrderIn($input_param,$_SESSION['cart_goods']);
		if($order_serial) {
			/*清空购物车*/
			unset($_SESSION['cart_goods']);
			/*清除游客购买标记*/
			unset($_SESSION['buy_member']);

			$this->output('order_serial',$order_serial);
			$this->output('price_count',$input_param['price_count']);
			$this->output('transport_name',$input_param['transport_name']);
			$this->output('pay_name',$input_param['pay_name']);

		} else {
			$this->showMessage($this->_lang['shopping_order_error'],$this->_configinfo['websit']['site_url']."/shopping.php",1,1000);
		}
		/*调出支付系统的相关内容*/
		$pay_type	= $this->obj_module_pay->getPayInfo(array('pay_id'=>$this->_input['pay_id']));
		if(file_exists("api/payarea/".$pay_type['pay_area_directory']."/".$pay_type['pay_code'].".php")) {
			include_once("api/payarea/".$pay_type['pay_area_directory']."/".$pay_type['pay_code'].".php");
			$pay_class_name	= $pay_type['pay_code'].'PayClass';
			$out_pay_type	= new $pay_class_name();

			$input_param['order_serial']	= $order_serial;
			$this->output('form_code',Common::nc_change_charset($out_pay_type->outForm($input_param,$pay_type,$this->_configinfo['websit']['site_url']),$this->_charset));
		}
		$this->showpage('cart04');

		/*发送邮件*/
		if($this->_configinfo['websit']['buy_goods_mail'] == '1' and $_SESSION['userinfo']['user_id'] != '') {
			include_once("system.class.php");
			$email_template	= new SystemClass();
			$user_email_template	= $email_template->getEmailTemplate(array('mail_template_name'=>"'buy_goods_mail'"));
			$order_array			= array('user_name'		=> $_SESSION['userinfo']['user_name'],
			'shop_name'		=> $this->_configinfo['websit']['site_name'],
			'order_sn'		=> $order_serial);
			$email_body				= Common::replaceMailContent($order_array,$user_email_template['mail_template_body']);

			/*邮件发送*/
			Common::shopnc_send_mail($_SESSION['userinfo']['user_email'],$this->_lang['shopping_cart3_order'],$email_body);
		}
	}
	/**
	 * 购物车检测
	 *
	 */
	private function checkCart() {
		if(empty($_SESSION['cart_goods'])) {
			$this->showMessage($this->_lang['shopping_cart_null'],'index.php',1,1000);
		}
	}
	/**
	 * 购物车购买数量下拉菜单
	 *
	 */
	private function selectOption($goods_num,$goods_storage) {
		$select_option	= '';
		$buy_num		= $goods_storage>30 ? 30 : $goods_storage;
		if($goods_num>30) $goods_num = 30;
		for($i=1;$i<=$buy_num;$i++) {
			$select_option	.= "<option value=\"".$i."\"";
			if($i == $goods_num) {
				$select_option .= " selected=\"selected\"";
			}
			$select_option .= " >".$i."</option>";
		}
		return  $select_option;
	}

	/**
	 * 货币的格式显示
	 *
	 */
	private function moneyFormat($money) {
		return number_format($money,2,'.','');
	}
	/**
	 * 优惠折扣计算
	 *
	 */
	private function goodsPriceDiscount($money) {
		$money	= ($money*($_SESSION['userinfo']['user_grade_discount'] ==''? 10 : $_SESSION['userinfo']['user_grade_discount']))/10;
		return $this->moneyFormat($money);
	}
	/**
	 * 换算价格，根据汇率换算
	 *
	 */
	private function changeGoodsPrice($money) {
		include(BasePath.'/share/currencies.php');
		if($this->_configinfo['websit']['shop_currency']!='') {
			$shop_currency	= $this->_configinfo['websit']['shop_currency'];
		} else {
			$shop_currency	= 1;
		}
		return $money*$currencies_set[$shop_currency]['currencies_rate'];
	}
	/**
	 * 对支付返回结果的处理
	 *
	 */
	private function modifyParment() {
		if(intval($this->_input['nc_order_serial']) == 0) {
			return false;
		}
		/*订单信息*/
		$order_info	= $this->obj_shop_order->getOrderInfo(array('order_serial'=>"'".trim($_GET['nc_order_serial'])."'"));

		/*调出支付系统的相关内容*/
		$pay_type	= $this->obj_module_pay->getPayInfo(array('pay_id'=>intval($this->_input['shopnccode'])));

		if(file_exists("api/payarea/".$pay_type['pay_area_directory']."/".$pay_type['pay_code'].".php")) {
			include_once("api/payarea/".$pay_type['pay_area_directory']."/".$pay_type['pay_code'].".php");
			$pay_class_name	= $pay_type['pay_code'].'PayClass';
			$out_pay_type	= new $pay_class_name();
			$result			= $out_pay_type->getParmentDo($order_info,$pay_type);
		}

		if($result) {
			include_once("order.class.php");
			$goods_order	= new OrderClass();
			$goods_order->orderState(array('action_type'=>'pay_ok','online_pay'=>1),$order_info['order_id']);
			/*发送email*/

			/*订单显示页面*/
			/*订单产品*/
			$order_goods	= $goods_order->showGoodsOrder(array('detail_order_id'=>$order_info['order_id']));
			$this->output('order_goods',$order_goods);
			/*订单信息*/
			$order_array	= $goods_order->getOrderList(array('order_id'=>$order_info['order_id']),'');

			$this->getlang("user_center_my_order_view");
			$order_state[1]		= $this->_lang['my_order_be_confirmed'];			//已确定
			$order_state[0]		= "<font color='red'>".$this->_lang['my_order_unconfirmed']."</font>";			//未确定
			$order_state1[1]	= $this->_lang['my_order_have_paid'];				//已付款
			$order_state1[0]	= "<font color='red'>".$this->_lang['my_order_not_paid']."</font>";				//未付款
			$order_state2[1]	= $this->_lang['my_order_yes_send'];				//已发货
			$order_state2[0]	= "<font color='red'>".$this->_lang['my_order_no_send']."</font>";				//未发货
			$order_state3[1]	= $this->_lang['my_order_already_filled'];			//已归档
			$order_state3[0]	= "<font color='red'>".$this->_lang['my_order_no_fill']."</font>";				//为归档
			$order_array[0]['order_state_txt']	= $order_state[$order_array[0]['order_state']];
			$order_array[0]['order_state1_txt']	= $order_state1[$order_array[0]['order_state1']];
			$order_array[0]['order_state2_txt']	= $order_state2[$order_array[0]['order_state2']];
			$order_array[0]['order_state3_txt']	= $order_state3[$order_array[0]['order_state3']];

			$this->output('order_array',$order_array[0]);
			$this->output('shopnc_pay_ok','1');

			$this->showpage('my_order_view');
			
			/*发送邮件*/
			if($this->_configinfo['websit']['pay_mail'] == '1') {
				include("system.class.php");
				$email_template		= new SystemClass();
				$pay_email_template	= $email_template->getEmailTemplate(array('mail_template_name'=>"'pay_mail'"));
				$pay_goods_array	= array('user_name'		=> $order_array[0]['receiver_name'],
												'shop_name'		=> $this->_configinfo['websit']['site_name'],
												'order_sn'		=> $order_array[0]['order_serial']);
				$email_body			= Common::replaceMailContent($pay_goods_array,$pay_email_template['mail_template_body']);
				/*发送邮件*/
				Common::shopnc_send_mail($order_array[0]['receiver_email'],$order_array[0]['receiver_name'],$email_body);
			}
		}
	}
}
$shopping = new ShowShopping();
$shopping->main();
unset($shopping);
?>