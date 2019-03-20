<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
    /** @var  crypto */
    public $crypto;

	/** @var account */
	public $account;

    /** @var propay_api */
    public $propay_api;

	/** @var  fullpayment */
	public $fullpayment;

	/** @var billing */
	public $billing;

	/** @var mailchimp */
	public $mailchimp;

	function __construct()
	{

		parent::__construct();
		$access = FALSE;
		$this->view_data['update'] = FALSE;
		if($this->client){
			//redirect('cprojects');
			redirect('projects');
		}elseif($this->user){
				if(in_array("dashboard", $this->view_data['module_permissions'])){
					$access = TRUE;
				}
			if(!$access && !empty($this->view_data['menu'][0])){
				redirect($this->view_data['menu'][0]->link);
			}elseif(empty($this->view_data['menu'][0])){
				$this->view_data['error'] = "true";
				$this->session->set_flashdata('message', 'error: You have no access to any modules!');
				redirect('login');
			}

		}else{
			redirect('login');
		}


		//Events
		$events = array();
		$date = date('Y-m-d', time());
		$eventcount = 0;

				if(in_array("messages", $this->view_data['module_permissions'])){
					$sql = 'SELECT pm.id, pm.`status`, pm.subject, pm.message, pm.`time`, pm.`recipient`, cl.`userpic` as userpic_c, us.`userpic` as userpic_u, us.`email` as email_u , cl.`email` as email_c , CONCAT(us.firstname," ", us.lastname) as sender_u, CONCAT(cl.firstname," ", cl.lastname) as sender_c
							FROM privatemessages pm
							LEFT JOIN clients cl ON CONCAT("c",cl.id) = pm.sender
							LEFT JOIN users us ON CONCAT("u",us.id) = pm.sender 
							WHERE pm.recipient = "u'.$this->user->id.'"AND pm.status != "deleted" 
							ORDER BY pm.`time` DESC LIMIT 6';
					$query = Privatemessage::find_by_sql($sql);
					$this->view_data["message"] = array_filter($query);
				}
				if(in_array("projects", $this->view_data['module_permissions'])){
					$sql = 'SELECT * FROM project_has_tasks WHERE status != "done" AND user_id = "'.$this->user->id.'" ORDER BY project_id';
					$taskquery = ProjectHasTask::find('all', array('conditions' => array('status = ? and user_id = ?', 'open', $this->user->id), 'order' => 'project_id desc'));
					$this->view_data["tasks"] = $taskquery;
					// $query = ProjectHasTask::find_by_sql($sql);
					// $this->view_data["tasks"] = array_filter($query);
				}



	}

	function year($year = FALSE)
	{ $this->index($year); }
	function index($year = FALSE)
	{


		$databasePrefix = $_SESSION['accountUrlPrefix'];

		if(in_array(substr($databasePrefix,0,1),explode(',','0,1,2,3,4,5,6,7,8,9')))
			$databasePrefix = 'z' . $databasePrefix;

		/** @var CI_DB_mysql_driver $primaryDatabase */
		$primaryDatabase = $this->load->database('primary', TRUE);

		$databaseName = $databasePrefix . '_' . ENVIRONMENT;

		$_SESSION['accountDatabasePrefix'] = $databasePrefix;

		/** @var CI_DB_mysql_driver $accountDatabase */
		$accountDatabase = $this->load->database($databaseName, TRUE);

		/** @var CI_DB_mysql_driver $primaryDatabase */
		$primaryDatabase = $this->load->database('primary', TRUE);

		$params = [
			'databaseName' => $databaseName,
			'primaryDatabase' => $primaryDatabase,
			'accountDatabase' => $accountDatabase,
	        'accountUrlPrefix' => $_SESSION['accountUrlPrefix'],
			'username' => $this->user->username,
			'session' => $this->session

		];

		$this->load->library( 'account', $params );
		$this->load->library( 'crypto', $params );
		$this->load->library( 'propay_api', $params );
		$this->load->library( 'billing', $params );

		//echo "<pre>";
		$this->account->isSubscriptionDue($_SESSION['accountUrlPrefix'], $this->billing);
		//var_export($this->account->getBilling()->getDue());
		//var_export($this->account->getBilling()->getSubscription());
		//var_export($this->account->getBilling()->getPromoCode());
		//var_export($this->account->getPlanType());
		//var_export($this->account->getAccount());
		//$this->account->loadLastPaidDate();
		//var_export($this->account->getLastPaidDays($this->account->getLastPaidDate()));
		//var_export($this->account->getLastPaidDate());

		//var_export($this->account->getAmountDue());
		//die;

		$params['account'] = $this->account;
		$params['crypto'] = $this->crypto;
		$params['propay_api'] = $this->propay_api;

		$this->load->library('fullpayment', $params);

		$merchantData = $this->fullpayment->isMerchantData()->getMerchantData();

		//echo "<pre>";
		//var_export($merchantData);
		$this->fullpayment->getMerchantInfo();
		$this->fullpayment->merchantInfo();
		$this->fullpayment->merchantBalances();
		$this->fullpayment->cryptoBalances($_SESSION['accountUrlPrefix'] . '_'  . $this->user->username);
		$this->view_data["fullpayment"] = $this->fullpayment;

		//var_export($this->fullpayment->getMerchantInfo());
		//var_export($this->fullpayment->getMerchantBalances());
		//var_export($this->fullpayment->getCryptoBalances());
		//var_export($this->fullpayment->getUserCryptoBalances($_SESSION['accountUrlPrefix'] . '_'  . $this->user->username));
		//die();
		if ($this->session->userdata( 'lastBilled' ) == null) {
			$this->view_data['titlePrefix'] = urlencode('Your free trial has ended, spera subscription due:');
		} else {
			$this->view_data['titlePrefix'] = urlencode('spera subscription due:');
		}

		if(!$year)
		{
			$year = date('Y', time());
		}
		$currentYearMonth = date('Y-m', time());
		$thismonth = date('m');
		$yearMonth = $year.'-'.$thismonth;


			// View Values
				$this->view_data["month"] = date('M');
				$this->view_data["year"] = $year;

			// Projects Stats
				$this->view_data["projects_open"] 	= Project::count(array('conditions' => array('progress < ?', 100)));
				$this->view_data["projects_all"] 	= Project::count();
			// Invoices Stats
				$this->view_data["invoices_open"] 	= Invoice::count(array('conditions' => array('status != ? AND status != ? AND estimate != ? AND id NOT IN (SELECT invoice_id FROM invoice_has_users)', 'Paid', 'Canceled', 1))); // Get all but canceled and Paid invoices
				$this->view_data["invoices_all"] 	= Invoice::count(array('conditions' => array('status != ? AND estimate != ? AND id NOT IN (SELECT invoice_id FROM invoice_has_users)', 'Canceled', 1))); // Get all but canceled invoices


			$this->view_data["stats"] 						= Invoice::getStatisticForYear($year);
			$this->view_data["stats_expenses"] 				= Invoice::getExpensesStatisticForYear($year);
			$this->view_data["payments"] 					= Invoice::paymentsForMonth($currentYearMonth);
			$this->view_data["paymentsOutstandingMonth"] 	= Invoice::outstandingPayments($currentYearMonth);
			$this->view_data["paymentsoutstanding"] 		= Invoice::outstandingPayments();
			$this->view_data["totalExpenses"] 				= Invoice::totalExpensesForYear($year);
			$this->view_data["totalIncomeForYear"] 			= Invoice::totalIncomeForYear($year);
			$this->view_data["totalProfit"] 				= $this->view_data["totalIncomeForYear"]-$this->view_data["totalExpenses"];

			$this->view_data["paymentsForThisMonthInPercent"] 	= ($this->view_data["payments"] == 0) ? 0 : @round($this->view_data["payments"]/$this->view_data["paymentsOutstandingMonth"]*100);
			$this->view_data["openProjectsPercent"] 			= ($this->view_data["projects_open"] == 0) ? 0 : @round($this->view_data["projects_open"]/$this->view_data["projects_all"]*100);
			$this->view_data["openInvoicePercent"] 				=  ($this->view_data["invoices_open"] == 0) ? 0 : @round($this->view_data["invoices_open"]/$this->view_data["invoices_all"]*100);
			$this->view_data["paymentsOutstandingPercent"] 		=  ($this->view_data["paymentsoutstanding"] == 0) ? 0 : @round($this->view_data["paymentsoutstanding"]/$this->view_data["totalIncomeForYear"]*100);
			$this->view_data["paymentsOutstandingPercent"]  = ($this->view_data["paymentsOutstandingPercent"] > 100) ? 100 : $this->view_data["paymentsOutstandingPercent"];

			//Format main statistic labels and values
			$line1 = '';
			$line2 = '';
		    $labels = '';
		    $untilMonth = $thismonth;
		    if($year != date('Y', time()))
		    {
		    	$untilMonth = 12;
			}
			if($untilMonth <= 2){
				$untilMonth = 3;
			}

		      for ($i = 01; $i <= $untilMonth; $i++) {
				$monthname = date_format(date_create_from_format('Y-m-d', '2016-'.$i.'-01'), 'M');
				$monthname = $this->lang->line('application_'.$monthname);
		        $num = "0";
		        $num2 = "0";
		        foreach ($this->view_data["stats"] as $value):
		          $act_month = explode("-", $value->paid_date);
		          if($act_month[1] == $i){
		            $num = sprintf("%02.2d", $value->summary);
		          }
		        endforeach;
		        foreach ($this->view_data["stats_expenses"] as $value):
		          $act_month = explode("-", $value->date_month);
		          if($act_month[1] == $i){
		            $num2 = sprintf("%02.2d", $value->summary);
		          }
		        endforeach;
		          $i = sprintf("%02.2d", $i);
		          $labels .= '"'.$monthname.'"';
		          $line1 .= $num;
		          $line2 .= $num2;
		          if($i != "12"){ $line1 .= ","; $line2 .= ","; $labels .= ",";}
		        }
		    $this->view_data["labels"] = $labels;
		    $this->view_data["line1"] = $line1;
		    $this->view_data["line2"] = $line2;



		    //Calendar
		    if($this->user->admin == 0){
			$comp_array = array();
			$thisUserHasNoCompanies = (array) $this->user->companies;
			if(!empty($thisUserHasNoCompanies)){
				$this->view_data['clientcounter'] = 0;
						foreach ($this->user->companies as $value) {
							array_push($comp_array, $value->id);
							$this->view_data['clientcounter']++;
						}
					$projects_by_client_admin = Project::find('all', array('conditions' => array('company_id in (?)', $comp_array)));

					//merge projects by client admin and assigned to projects
					$result = array_merge( $projects_by_client_admin, $this->user->projects );
					//duplicate objects will be removed
					$result = array_map("unserialize", array_unique(array_map("serialize", $result)));
					//array is sorted on the bases of id
					sort( $result );

					$projects = $result;

					//get new tickets
					$options = array('conditions' => array('status != ? AND company_id in (?)',"closed",$comp_array), 'limit' => 5);
					$options2 = array('conditions' => array('status != ? AND company_id in (?)',"closed",$comp_array));
					$this->view_data['ticket'] = Ticket::find('all', $options);
					$this->view_data['ticketcounter'] = Ticket::count($options2);
					$options3 = array('conditions' => array('user_id in (?)', $this->user->id));
					$userProjects = ProjectHasWorker::find_by_('all', $options3);
					if (isset($userProjects->project_id)) $userProjects = [$userProjects];
                    $projectIds = array();
					foreach ($userProjects as $project) {
						array_push($projectIds, $project->project_id);
					}
					$this->view_data['recent_activities'] = ProjectHasActivity::find('all', array('conditions' => array('project_id in (?)', $projectIds), 'order' => 'datetime desc', 'limit' => 10));



			}else{
				$projects = $this->user->projects;
				$options = array('conditions' => array('status != ? AND user_id in (?)',"closed",$this->user->id), 'limit' => 5);
				$options2 = array('conditions' => array('status != ? AND user_id in (?)',"closed",$this->user->id));

				$this->view_data['ticket'] = Ticket::find('all', $options);
				$this->view_data['ticketcounter'] = Ticket::count($options2);
				$this->view_data['clientcounter'] = 0;
				$options3 = array('conditions' => array('user_id in (?)', $this->user->id));
				$userProjects = ProjectHasWorker::find_by_('all', $options3);
				$projectIds = array();
				if (is_array($userProjects)) {
					foreach ($userProjects as $value) {
						array_push($projectIds, $value->project_id);
					}
				}

				if (count($projectIds) > 0) {
                    $this->view_data['recent_activities'] = ProjectHasActivity::find('all', array('conditions' => array('project_id in (?)', $projectIds), 'order' => 'datetime desc', 'limit' => 10));
                } else {
                    $this->view_data['recent_activities'] = [];
                }
			}
		}else{
			$projects = Project::all();
			$options = array('conditions' => array('status != ?',"closed"), 'limit' => 5);
			$options2 = array('conditions' => array('status != ?',"closed"));

			$this->view_data['ticket'] = Ticket::find('all', $options);
			$this->view_data['ticketcounter'] = Ticket::count($options2);
			$this->view_data['clientcounter'] = Company::count(array('conditions' => array('inactive=?','0')));
			$this->view_data['recent_activities'] = ProjectHasActivity::find('all', array('order' => 'datetime desc', 'limit' => 10));

		}
		$project_events = "";
		foreach ($projects as $value) {
			$descr = preg_replace( "/\r|\n/", "", $value->description );
			$project_events .= "{
                          title: '".$this->lang->line('application_project').": ".addslashes($value->name)."',
                          start: '".$value->start."',
                          end: '".$value->end."',
                          url: '".base_url()."projects/view/".$value->id."',
                          className: 'project-event',
                          description: '".addslashes($descr)."'
                      },";
		}

		//events
		$events = Event::all();

		$event_list = "";
		$event_count_for_today = 0;
		foreach ($events as $value) {
			if(substr($value->start, 0, 10) == date('Y-m-d', time())){
				$event_count_for_today++;
			}

			$event_list .= "{
                          title: '".addslashes($value->title)."',
                          start: '".$value->start."',
                          end: '".$value->end."',
                          url: '".base_url()."calendar/edit_event/".$value->id."',
                          className: '".$value->classname."',
                          modal: 'true',
                          description: '".addslashes(preg_replace( "/\r|\n/", "", $value->description))."',

                      },";
		}

		$this->view_data['project_events'] = $project_events;
		$this->view_data['events_list'] = $event_list;
		$this->view_data['event_count_for_today'] = $event_count_for_today;
		$this->view_data["account"] = $this->account;
		$this->setTitle( "Hi " . ucwords( $this->user->firstname ) . ", Welcome back!" );
		$this->content_view = 'dashboard/dashboardV2';

	}

	function propay()
	{
		$paymentType = $_REQUEST['paymentType'];
		$amount_due = $_REQUEST['amountDue'];
		$titlePrefix = (isset($_REQUEST['titlePrefix'])) ? $_REQUEST['titlePrefix'] : 'SPERA SUBSCRIPTION DUE:';

		//generate a spera invoice for this person, or lookup.
		//$this->view_data['invoices'] = Invoice::find_by_id( 1 ); //todo: stuffing invoice number for now

		$this->view_data['amount_due']  = $amount_due;
		//$this->view_data['sum'] = $sum;
		$this->view_data['paymentType'] = $paymentType;
		$this->theme_view       = 'modal';

		$this->view_data['form_action'] = 'dashboard/propay';

		if ($paymentType == 'ach') {
			$this->view_data['title'] = $titlePrefix . ' ' . $this->lang->line('application_pay_with_ach');
		} else {
			$this->view_data['title'] = $titlePrefix . ' ' . $this->lang->line('application_pay_with_credit_card');
		}
		$this->content_view             = 'dashboard/_propay_hpp';
	}


}


