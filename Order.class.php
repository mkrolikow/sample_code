<?

require_once('ShopTop.class.php');
require_once('ImportExport.class.php');
require_once('Customer.class.php');
require_once('Address.class.php');
require_once('InPost.class.php');
require_once('SubscriptionManager.php');

// This class is currently only used by admin, but there is 'order'-logic
//  in ShopClient such as for inserting orders. Therefore, we only do requireAdmin() per method
//  here, so this class can encapsulate all order functionality in the future.
class Order extends ShopTop
{
    const ACTION_UPDATE_ORDER = 'UPDATE_ORDER';
    const ACTION_DELETE_ORDER = 'DELETE_ORDER';

    const EXTENDED_STATUS_CANCELLED = "cancelled";
    const EXTENDED_STATUS_COLLECTED = "collected";
    const EXTENDED_STATUS_COMPLETED = "completed";
    const EXTENDED_STATUS_DELAYED = "delayed";
    const EXTENDED_STATUS_FAILED = "failed";
    const EXTENDED_STATUS_INPROGRESS = "inprogress";
    const EXTENDED_STATUS_MERGED = "merged";
    const EXTENDED_STATUS_NEW = "new";
    const EXTENDED_STATUS_ONHOLD = "onhold";
    const EXTENDED_STATUS_OUTOFSTOCK = "outofstock";
    const EXTENDED_STATUS_PAID = "paid";
    const EXTENDED_STATUS_PENDING = "pending";
    const EXTENDED_STATUS_PROCESSED = "processed";
    const EXTENDED_STATUS_RESENT = "resent";
    const EXTENDED_STATUS_REST = "rest";
    const EXTENDED_STATUS_SENT = "sent";
    const EXTENDED_STATUS_UNKNOWN = "unknown";

    // Order expiry is normally expected to be handled by PayPro. This is however not the case for certain payment
    // methods, where responsibility falls on the shop/daemon instead.
    public static $manual_expiry_rules = [
        "payment_methods" => [PAYMENT_IDEAL, PAYMENT_TRANSFER, PAYMENT_INVOICE, PAYMENT_NONE],
        "expiry_time" => 30 // Days.
    ];

    // These are the selectable statuses - 'Cancelled' and 'InProgress' are not included
    public $statuses = ['Ny', 'Betalt', 'OnHold', 'Plukkes', 'Sendt', 'Rest', 'Delayed', 'OutOfStock', 'Pending',
        'Preauth'];
    // These are the statuses that are 'completed' - used for sorting
    public $completedStatuses = array('Sendt', 'Cancelled', 'Merged');
    public $all_statuses = array('Ny', 'Betalt', 'OnHold', 'Plukkes', 'Rest', 'Sendt', 'Cancelled', 'InProgress',
        'Delayed', 'Merged', 'OutOfStock', 'Resend', 'Pending');
    // Pending is for paypal problems, Completed are for all completed credit card payments,
    // Auth is for funds reserved (but not claimed) for BBS etc.
    // Svea currently use the payment method actually used in the pay status, with a great many
    // different types based on the card used and so forth, so this list is not exhaustive;
    // It is also only currently present as documentation. The Svea statuses are present in
    // the class Sveawebpayment.class.php 
    public $pay_statuses = array('', 'Pending', 'Completed', 'Auth');
    // This enumeration is currently only present as documentation. 
    public $payment_methods = array('paypal', 'bbs', 'postoppkrav', 'invoice', 'prepaid', 'sveainteractive', 'sveacard',
        'sveainvoice', 'sveapartpayment', 'sveainternetbank');
    public $incompleteStatuses;

    // used for logging order change actions
    protected $previousOrderData;

    // This is a cache-var. Once populated, it will contain a list of all supported order transformations (actions),
    // mostly tailored towards front end use, along with rules mandating their availability on a per-order basis.
    // Defined dynamically due to things like $incompleteStatuses, which are only available at runtime.
    public $action_list;

    function __construct()
    {
        parent::__construct();

        // Uncomment this and all usages to enable logging of all database modifications of the order table.
        // log database operations to this log. 
        // $orderlog = new Logger("orderlog.txt", "log");
        // $this->orderlog = $orderlog;

        global $total_pages;
        global $search;
        global $orderby;
        global $order_status;
        global $prpage;
        global $p;

        // Calculate the incomplete statuses. This can't be done in a normal instance
        //  variable initalization above because of syntax issues 
        $this->incompleteStatuses = array_diff($this->statuses, $this->completedStatuses);

        $this->smarty->assign("all_statuses", $this->all_statuses);
        $this->smarty->assign("statuses", $this->statuses);
    }

    
    function __call($m, $a)
    {
        if (function_exists($m)) {
            call_user_func_array($m, $a);
        } else {
            print "Funksjon $m er ikke definert foreløpig. Den kommer nok sikkert snart";
        }
    }

    function start()
    {
        // Admin-only method.
        $this->requireAdmin();
        $this->orders_list();
    }

    // Return a date in ISO format if valid, for Mysql.
    function checkDate($datestring)
    {
        list($d, $m, $y) = sscanf($datestring, "%d/%d/%d");
        if ($d && $m && $y) {
            return sprintf("%4d-%02d-%02d", $y, $m, $d);
        }
        return false;
    }

    function putOnHold()
    {
        $this->requireAdmin();

        try {
            $order = self::get($_GET['orderid'], false);
            $result = $this->suspendOrder($order);
            if (!$result) throw new Exception("Failed to suspend the order.");
        } catch (Exception $ex) {
            $this->flash($ex->getMessage());
        }

        header("Location: /admin/order.php"
            . (isset($order['id']) ? "?do=order_details_list&order_id={$order['id']}" : "?do=orders_list"));
    }

    function releaseNew()
    {
        $this->requireAdmin();
        $id = sprintf("%d", $_GET['orderid']);

        try {
            $this->releaseOrder($id);
        } catch (Exception $ex) {
            exit($ex->getMessage());
        }

        header("Location: /admin/order.php?do=orders_list");
        exit();
    }

    

    function orders_list()
    {
        // Admin-only method.
        $this->requireAdmin();

        // If any outstanding card payments are older than the limit, cancel them. IOK 2009-12-16
        $this->deleteAbandonedCardPayments();

        // Collect all the payment methods previously seen in the shop.
        $payment_methods = $this->getPaymentMethods();

        // Search and filter fields:
        $order_status = isset($_GET['order_status']) ? $_GET['order_status'] : $_POST['order_status'];
        $fromdate = isset($_GET['fromDate']) ? $_GET['fromDate'] : $_POST['fromDate'];
        $todate = isset($_GET['toDate']) ? $_GET['toDate'] : $_POST['toDate'];
        $search = isset($_GET['search']) ? $_GET['search'] : $_POST['search'];
        $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : $_POST['orderby'];
        $orderdir = isset($_GET['orderdir']) ? $_GET['orderdir'] : $_POST['orderdir'];
        $method = isset($_GET['method']) ? $_GET['method'] : $_POST['method'];
        $oidsearch = isset($_GET['oidsearch']) ? $_GET['oidsearch'] : $_POST['oidsearch'];
        $shippingnosearch = isset($_GET['shippingnosearch']) ? $_GET['shippingnosearch'] : $_POST['shippingnosearch'];
        $custnosearch = isset($_GET['custnosearch']) ? $_GET['custnosearch'] : $_POST['custnosearch'];
        $paymentmethodsearch = isset($_GET['paymentmethodsearch']) ?
            $_GET['paymentmethodsearch'] : $_POST['paymentmethodsearch'];


        $isofromdate = $this->checkDate($fromdate);
        $isotodate = $this->checkDate($todate);

        if (empty($orderby)) $orderby = 'O.id';
        if (empty($orderdir)) $orderdir = 'desc';
        $switchorder = $orderdir == 'asc' ? 'desc' : 'asc';


        $orders = $this->search_orders();

        if (strcmp($method, "search") != 0 && !empty($method)) {
            $io = new ImportExport($this);
            $io->exportOrders($orders);
            return;
        }


        $this->smarty->assign("fromDate", $fromdate);
        $this->smarty->assign("toDate", $todate);
        $this->smarty->assign("search", $search);
        $this->smarty->assign("oidsearch", $oidsearch);
        $this->smarty->assign("shippingnosearch", $shippingnosearch);
        $this->smarty->assign("custnosearch", $custnosearch);
        $this->smarty->assign("paymentmethodsearch", $paymentmethodsearch);
        $this->smarty->assign("order_status", $order_status);
        $this->smarty->assign("orderby", $orderby);
        $this->smarty->assign("orderdir", $orderdir);
        $this->smarty->assign("switchorder", $switchorder);
        $this->smarty->assign("prpage", $this->prpage);
        $this->smarty->assign("lastpage", $this->total_pages);
        $this->smarty->assign("thispage", $this->p);
        $this->smarty->assign("prevpage", $this->p - 1);
        $this->smarty->assign("nextpage", $this->p + 1);
        $this->smarty->assign("paymentmethods", $payment_methods);

        $this->smarty->assign("orders", $orders);
        $this->smarty->assign("order_status", $order_status);
        $this->display("orders_list.tpl");
    }
   
}
