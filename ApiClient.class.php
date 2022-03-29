<?php

require_once("ShopClient.class.php");
require_once("OFDIBS.class.php");
require_once("ApiResponse.class.php");

/**
 * The main webshop API controller.
 * Not much of a "client" in fairness - the name alludes to "ShopClient".
 */
class ApiClient
{
    private $shop;

    /**
     * Key used to authenticate against target shop API.
     * @TODO make me configurable (this is temporary hack)
     */
    const SHOP_API_KEY = 'wertyuiofgvhb45867899080ghj';

    public function __construct()
    {
        $this->shop = new Shop();
    }

    public function respond($request)
    {
        if (!$this->authorizeRequest($request)) return ApiResponse::unauthorized("Not ok at all!");

        $action = $request->get("do");
        $params = $request->get("params");

        $this->deployGlobalParams($params);

        // Unrecognized action.
        if (!method_exists($this, $action)) {
            return ApiResponse::notFound();
        }

        return $this->$action($params);
    }

    private function deployGlobalParams($params) {
        if (isset($params['p64Xy_client_ip'])) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = $params['p64Xy_client_ip'];
        }
        if (isset($params['p64Xy_admin_login'])) {
            $_SESSION['adminuser'] = $params['p64Xy_admin_login'];
            $_SESSION['isAdmin'] = true;
        }
    }

    /**
     * @TODO this is just temporary hack. Surely needs to be done
     * @return boolean
     */
    private function authorizeRequest($request)
    {
        $api_key = $request->get("api_key");
        return hash_equals($api_key, self::SHOP_API_KEY);
    }

    private function internalError($error)
    {
        $this->shop->mulogger->logError($error);
        return ApiResponse::internalError($error);
    }

    private function inputDataError($error)
    {
        $this->shop->mulogger->logError($error);
        return ApiResponse::inputDataError($error);
    }

    private function unauthorizedError($error)
    {
        $this->shop->mulogger->logError($error);
        return ApiResponse::unauthorized($error);
    }

    private function deploySession($session_data, $customer_no = null) {
        $data = unserialize(base64_decode($session_data));
        if (is_array($data)) {
            $_SESSION = array_merge($_SESSION ?? [], $data);
        }
        elseif ($data === false) { // failed deserialization
            return $this->inputDataError("Invalid session data");
        }
        if ($customer_no) {
            return $this->initSessionCustomer($customer_no);
        }
        return true;
    }

    private function initSessionCustomer($customer_no) {
        $customer = Customer::getCustomerInfo($customer_no, true);
        if (!$customer) {
            return $this->inputDataError("Invalid customer number");
        }
        $_SESSION['customer'] = $customer;
        $_SESSION['custno'] = $customer_no;
        $_SESSION['customer_id'] = $_SESSION['customer']['id'];
        $_SESSION['customerdiscount'] = $this->shop->loadDiscountByCustomer($customer);

        return true;
    }

    private function packSession() {
        return base64_encode(serialize($_SESSION));
    }

    /**
     * Fetches a customer's autoorder.
     * @param array $parameters
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    private function getAutoorder($parameters)
    {
        try {

            $ao = AutoOrder::getByCustomerRedux($parameters["custNo"] ?? null, $this->shop);
            if ($ao) {
                $customer = CustomerClient::getCustomerInfo($parameters["custNo"], true);
                $cleanCart = $this->shop->substituteMonthlyInCart($ao['orderlines'], $customer);
                $ao['orderlines'] = $cleanCart;
            }
            return ApiResponse::ok($ao);
        } catch (Exception $ex) {
            // Since this method call shouldn't raise errors - if something goes boom, it must be an internal problem.
            return $this->internalError($ex->getMessage());
        }
    }

    

    private function checkProductsVisibility($parameters) {
        $cust_no = $parameters['custNo'] ?? null;
        if ($cust_no) {
            $this->initSessionCustomer($cust_no);
        }
        $ids = array_map('intval', explode(',', $parameters['productIds']));
        $vis = array_map(function($id) {
            return ['id' => $id, 'visible' => $this->shop->checkProductVisibility($id)];
        }, $ids);
        return ApiResponse::ok(
                ['visibility' => $vis]
            );
    }

    

    private function findDpdDeliveryPoint($parameters) {
        $_SESSION['login'] = 1;
        $custNo = $parameters['custNo'];
        $customer = Customer::getCustomerInfo($custNo, true);
        $_SESSION['login'] = 1;
        $_SESSION['customer_id'] = $customer['id'];

        foreach (['Street', 'City', 'ZipCode', 'HouseNo'] as $var) {
            $_GET[$var] = $parameters[$var];
        }

        $this->shop->findDpdDeliveryPoint();
    }

    
}
