<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Tickets extends MY_Controller {

	function __construct()
	{
		parent::__construct();
		$access = FALSE;
		$link = '/'.$this->uri->uri_string();

		if($this->client){
			if($this->input->cookie('fc2_link') != ""){
				$link = str_replace("/tickets/", "/ctickets/", $link);
				redirect($link);
			}
			redirect('ctickets');
		}elseif($this->user){
			foreach ($this->view_data['menu'] as $key => $value) {
				if($value->link == "tickets"){ $access = TRUE;}
			}
			if(!$access){redirect('login');}
		}else{

			$cookie = array(
                   'name'   => 'fc2_link',
                   'value'  => $link,
                   'expire' => '500',
               );

			$this->input->set_cookie($cookie);
			redirect('login');

		}
		$this->view_data['submenu'] = array(

				 		$this->lang->line('application_my_tickets') => 'tickets/filter/assigned',
				 		$this->lang->line('application_open') => 'tickets/filter/open',
				 		$this->lang->line('application_ticket_status_reopened') => 'tickets/filter/reopened',
				 		$this->lang->line('application_closed') => 'tickets/filter/closed'
				 		);
		$this->load->database();

		$this->view_data['tickets_assigned_to_me'] = Ticket::count(array('conditions' => 'user_id = '.$this->user->id.' and status != "closed"'));
		$this->view_data['tickets_in_my_queue'] = Ticket::count(array('conditions' => 'queue_id = '.$this->user->queue.' and status != "closed"'));

		$now = time();
		$beginning_of_week = strtotime('last Monday', $now); // BEGINNING of the week
		$end_of_week = strtotime('next Sunday', $now) + 86400; // END of the last day of the week
		$this->view_data['tickets_opened_this_week'] = Ticket::find_by_sql('select count(id) AS "amount", DATE_FORMAT(FROM_UNIXTIME(`created`), "%w") AS "date_day", DATE_FORMAT(FROM_UNIXTIME(`created`), "%Y-%m-%d") AS "date_formatted" from tickets where created >= "'.$beginning_of_week.'" AND created <= "'.$end_of_week.'" Group By date_day, created');
		//$this->view_data['tickets_closed_this_week'] = Ticket::find_by_sql('select count(id) AS "amount", DATE_FORMAT(FROM_UNIXTIME(`created`), "%w") AS "date_day", DATE_FORMAT(FROM_UNIXTIME(`created`), "%Y-%m-%d") AS "date_formatted" from tickets where created >= "'.$beginning_of_week.'" AND created <= "'.$end_of_week.'" ');


	}
	function index1()
	{

		if($this->user->admin == 0){
			$thisUserHasNoCompanies = (array) $this->user->companies;
					if(!empty($thisUserHasNoCompanies)){
				$comp_array = array();
				foreach ($this->user->companies as $value) {
					array_push($comp_array, $value->id);
				}
				$options = array('conditions' => array('status != ? AND company_id in (?)',"closed",$comp_array));
				$this->view_data['ticket'] = Ticket::find('all', $options);
				$this->view_data['ticketFilter'] = $this->lang->line('application_all');
			}else{
				$this->view_data['ticket'] = $this->user->tickets;
				$this->view_data['ticketFilter'] = $this->lang->line('application_my_tickets');
			}
		}else{
			$options = array('conditions' => array('status != ?',"closed"));
			$this->view_data['ticket'] = Ticket::find('all', $options);
			$this->view_data['ticketFilter'] = $this->lang->line('application_all');

		}

		$this->view_data['queues'] = Queue::find('all',array('conditions' => array('inactive=?','0')));
		$this->content_view = 'tickets/all';



	}
	function index() {
		$this->setTitle('Tickets');
		$this->content_view = 'projects/work';
	}
	function data() {
		$tickets = array_map(function($ticket) {
			$attributes = $ticket->attributes();
			$attributes['text'] = strip_tags($ticket->text);
			$attributes['created'] = date("Y-m-d", $ticket->created);

			if($ticket->client_id)
				$attributes['client'] = $ticket->client->attributes();
			if($ticket->user_id)
				$attributes['user'] = $ticket->user->attributes();
			if($ticket->queue_id)
				$attributes['queue'] = $ticket->queue->attributes();
			if($ticket->type_id)
				$attributes['type'] = $ticket->type->attributes();

			$attributes['articles'] = array_map( function ( $article ) {
				$attributes = $article->attributes();
				$attributes['message'] = strip_tags($article->message);
				$attributes['datetime'] = date("Y-m-d", $article->datetime);

				if($article->user)
					$attributes['user_info'] = $article->user->attributes();

				return $attributes;
			}, $ticket->ticket_has_articles );

			return $attributes;
		}, Ticket::all());

		echo json_encode( [
			                  'status'  => true,
			                  'tickets' => $tickets
		                  ] );
		die();
	}
	function details($id = false) {
		$ticket = Ticket::find($id);
		$attributes = $ticket->attributes();

		$attributes['text'] = strip_tags($ticket->text);
		$attributes['created'] = date("Y-m-d", $ticket->created);

		if($ticket->client_id)
			$attributes['client'] = $ticket->client->attributes();
		if($ticket->user_id)
			$attributes['user'] = $ticket->user->attributes();
		if($ticket->queue_id)
			$attributes['queue'] = $ticket->queue->attributes();
		if($ticket->type_id)
			$attributes['type'] = $ticket->type->attributes();

		$attributes['articles'] = array_map( function ( $article ) {
			$attributes = $article->attributes();
			$attributes['message'] = strip_tags($article->message);
			$attributes['datetime'] = date("Y-m-d", $article->datetime);

			if($article->user)
				$attributes['user_info'] = $article->user->attributes();

			return $attributes;
		}, $ticket->ticket_has_articles );

		echo json_encode( [
			                  'status' => true,
			                  'data'   => $attributes
		                  ] );
		die();
	}
	function edit($id = false) {
		$this->setTitle('Work');
		$this->content_view = 'projects/work';
	}
	function update($id = false) {
		if($_POST) {
			$ticket = Ticket::find($id);
			$ticket->update_attributes($_POST);

			echo json_encode( [
				                  'status' => true
			                  ] );
		}
		die();
	}
	function queues($id)
	{
		if($this->user->admin == 0){
			$comp_array = array();
			$thisUserHasNoCompanies = (array) $this->user->companies;
					if(!empty($thisUserHasNoCompanies)){
				foreach ($this->user->companies as $value) {
					array_push($comp_array, $value->id);
				}
				if($this->user->queue == $id){

					$options = array('conditions' => array('status != ? AND queue_id = ? ',"closed", $id));
				}else{
				$options = array('conditions' => array('status != ? AND queue_id = ? AND company_id in (?)',"closed", $id, $comp_array));
				}
			}else{
				if($this->user->queue == $id){
					$options = array('conditions' => array('status != ? AND queue_id = ? ',"closed", $id));
				}else{
					$options = array('conditions' => array('status != ? AND queue_id = ? AND user_id = ?',"closed", $id, $this->user->id));
				}

			}
		}else{
			$options = array('conditions' => array('status != "closed" AND queue_id = '.$id));
			$this->view_data['queues'] = Queue::find('all',array('conditions' => array('inactive=?','0')));
		}

		$this->view_data['ticketFilter'] = $this->lang->line('application_all');
		$this->view_data['activeQueue'] = Queue::find_by_id($id);
		$this->view_data['queues'] = Queue::find('all',array('conditions' => array('inactive=?','0')));
		$this->view_data['ticket'] = Ticket::find('all', $options);
		$this->content_view = 'tickets/all';
	}
	function filter($condition)
	{
		$this->view_data['ticketFilter'] = $this->lang->line('application_all');
		$this->view_data['queues'] = Queue::find('all',array('conditions' => array('inactive=?','0')));
		switch ($condition) {
			case 'open':
				$option = 'status = "open"';
				$this->view_data['ticketFilter'] = $this->lang->line('application_open');
				break;
			case 'closed':
				$option = 'status = "closed"';
				$this->view_data['ticketFilter'] = $this->lang->line('application_closed');
				break;
			case 'reopened':
				$option = 'status = "reopened"';
				$this->view_data['ticketFilter'] = $this->lang->line('application_ticket_status_reopened');
				break;
			case 'assigned':
				$option = 'status != "closed" AND user_id = '.$this->user->id;
				$this->view_data['ticketFilter'] = $this->lang->line('application_my_tickets');
				break;
		}
		if($this->user->admin == 0){
			$comp_array = array();
			$thisUserHasNoCompanies = (array) $this->user->companies;
					if(!empty($thisUserHasNoCompanies)){
				foreach ($this->user->companies as $value) {
					array_push($comp_array, $value->id);
				}
				$options = array('conditions' => array($option.' AND company_id in (?)',$comp_array));
			}else{
				$options = array('conditions' => array($option.' AND (user_id = ? OR queue_id = ?)',$this->user->id, $this->user->queue));
			}

		}else{
			$options = array('conditions' => array($option));
		}


		$this->view_data['ticket'] = Ticket::find('all', $options);
		$this->content_view = 'tickets/all';
	}
	function create()
	{
		if($_POST){
			$config['upload_path'] = './files/media/';
			$config['encrypt_name'] = TRUE;
			$config['allowed_types'] = '*';

			$this->load->library('upload', $config);
			$this->load->helper('notification');

			unset($_POST['userfile']);
			unset($_POST['file-name']);

			unset($_POST['send']);
			unset($_POST['_wysihtml5_mode']);
			unset($_POST['files']);

			$client = Client::find_by_id($_POST['client_id']);
			$user = User::find_by_id($_POST['user_id']);
			if(isset($client->email)){ $_POST['from'] = $client->firstname.' '.$client->lastname.' - '.$client->email; } else {$_POST['from'] = $this->user->firstname.' '.$this->user->lastname.' - '.$this->user->email;}

			if(isset($_POST['notify_agent']) && $user){
				$notify_agent = "true";
				}
				if(isset($_POST['notify_client'])){
				$notify_client = "true";
				}
			unset($_POST['notify_agent']);
			unset($_POST['notify_client']);
			if(isset($client->company->id)){
				$_POST['company_id'] = $client->company->id;
			}
			$_POST['created'] = time();
			$_POST['updated'] = "1";
			$_POST['subject'] = htmlspecialchars($_POST['subject']);
			$ticket_reference = Setting::first();
			$_POST['reference'] = $ticket_reference->ticket_reference;
			$_POST['status'] = $ticket_reference->ticket_default_status;
			$ticket = Ticket::create($_POST);
			$new_ticket_reference = $_POST['reference']+1;
			$ticket_reference->update_attributes(array('ticket_reference' => $new_ticket_reference));
			$email_attachment = false;
			if ( ! $this->upload->do_upload())
						{
							$error = $this->upload->display_errors('', ' ');
							$this->session->set_flashdata('message', 'error:'.$error);

						}
						else
						{
							$data = array('upload_data' => $this->upload->data());

							$attributes = array('ticket_id' => $ticket->id, 'filename' => $data['upload_data']['orig_name'], 'savename' => $data['upload_data']['file_name']);
							$attachment = TicketHasAttachment::create($attributes);
							$email_attachment = $data['upload_data']['file_name'];
						}


       		if(!$ticket){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_create_ticket_error'));
       					redirect('tickets');
       					}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_create_ticket_success'));

       		if(isset($notify_agent)){
				send_ticket_notification($user->email, '[Ticket#'.$ticket->reference.'] - '.$_POST['subject'], $_POST['text'], $ticket->id, $email_attachment);
				}
				if(isset($notify_client)){
				send_ticket_notification($client->email, '[Ticket#'.$ticket->reference.'] - '.$_POST['subject'], $_POST['text'], $ticket->id, $email_attachment);
				}

       			//redirect('tickets/view/'.$ticket->id);
       			redirect('tickets/edit/'.$ticket->id);
       			}

		}else
		{
			if($this->user->admin != 1){
				$comp_array = array();
				$thisUserHasNoCompanies = (array) $this->user->companies;
					if(!empty($thisUserHasNoCompanies)){
					foreach ($this->user->companies as $value) {
					array_push($comp_array, $value->id);
					}
					$this->view_data['clients'] = Client::find('all',array('conditions' => array('inactive=? AND company_id in (?)','0', $comp_array)));
				}else{
					$this->view_data['clients'] = (object) array();
				}
			}else{
				$this->view_data['clients'] = Client::find('all',array('conditions' => array('inactive=?','0')));
			}
			$this->view_data['users'] = User::find('all',array('conditions' => array('status=?','active')));
			$this->view_data['queues'] = Queue::find('all',array('conditions' => array('inactive=?','0')));
			$this->view_data['types'] = Type::find('all',array('conditions' => array('inactive=?','0')));
			$this->view_data['settings'] = Setting::first();

			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_create_ticket');
			$this->view_data['form_action'] = 'tickets/create';
			$this->content_view = 'tickets/_ticket';
		}
	}
	function assign($id = FALSE)
	{
		$this->load->helper('notification');
		if($_POST){
			unset($_POST['send']);
			unset($_POST['_wysihtml5_mode']);
			$id = $_POST['id'];
			unset($_POST['id']);
			unset($_POST['files']);
			$user = User::find_by_id($_POST['user_id']);
			$assign = Ticket::find_by_id($id);
			$attr = array('user_id' => $_POST['user_id']);
			$assign->update_attributes($attr);

			if(isset($_POST['notify']) && $user){
			send_ticket_notification($user->email, '[Ticket#'.$assign->reference.'] - '.$_POST['subject'], $_POST['message'], $id);
			}
			unset($_POST['notify']);
			$_POST['subject'] = htmlspecialchars($_POST['subject']);
			$_POST['datetime'] = time();
			$_POST['from'] = $this->user->firstname." ".$this->user->lastname.' - '.$this->user->email;
			$_POST['reply_to'] = $this->user->email;
			$_POST['ticket_id'] = $id;
			$_POST['to'] = $_POST['user_id'];
			unset($_POST['user_id']);
			$article = TicketHasArticle::create($_POST);
       		if(!$assign){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_save_ticket_error'));}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_assign_ticket_success'));}
			redirect('tickets/view/'.$id);
		}else
		{
			$this->view_data['users'] = User::find('all',array('conditions' => array('status=?','active')));
			$this->view_data['ticket'] = Ticket::find($id);
			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_assign_to_agents');
			$this->view_data['form_action'] = 'tickets/assign';
			$this->content_view = 'tickets/_assign';
		}
	}
	function client($id = FALSE)
	{
		$this->load->helper('notification');
		if($_POST){
			unset($_POST['send']);
			unset($_POST['_wysihtml5_mode']);
			unset($_POST['files']);
			$id = $_POST['id'];
			unset($_POST['id']);
			$client = Client::find_by_id($_POST['client_id']);
			$assign = Ticket::find_by_id($id);
			$attr = array('client_id' => $client->id, 'company_id' => $client->company->id);
			$assign->update_attributes($attr);

			if(isset($_POST['notify'])){
			send_ticket_notification($client->email, '[Ticket#'.$assign->reference.'] - '.$_POST['subject'], $_POST['message'], $assign->id);
			$_POST['internal'] = "0";
			}
			unset($_POST['notify']);
			$_POST['subject'] = htmlspecialchars($_POST['subject']);
			$_POST['datetime'] = time();
			$_POST['from'] = $this->user->firstname." ".$this->user->lastname.' - '.$this->user->email;
			$_POST['reply_to'] = $this->user->email;
			$_POST['ticket_id'] = $id;
			$_POST['to'] = $_POST['client_id'];
			unset($_POST['client_id']);
			$article = TicketHasArticle::create($_POST);
       		if(!$assign){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_save_ticket_error'));}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_assign_ticket_success'));}
			redirect('tickets/view/'.$id);
		}else
		{
			if($this->user->admin != 1){
				$comp_array = array();
				foreach ($this->user->companies as $value) {
					array_push($comp_array, $value->id);
				}
				$this->view_data['clients'] = Client::find('all',array('conditions' => array('inactive=? AND company_id in (?)','0', $comp_array)));
			}else{
				$this->view_data['clients'] = Client::find('all',array('conditions' => array('inactive=?','0')));
			}
			$this->view_data['ticket'] = Ticket::find($id);
			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_client');
			$this->view_data['form_action'] = 'tickets/client';
			$this->content_view = 'tickets/_client';
		}
	}
	function queue($id = FALSE)
	{
		$this->load->helper('notification');
		if($_POST){
			unset($_POST['send']);
			unset($_POST['_wysihtml5_mode']);
			unset($_POST['files']);
			$id = $_POST['id'];
			unset($_POST['id']);
			$ticket = Ticket::find_by_id($id);
			$attr = array('queue_id' => $_POST['queue_id']);
			$ticket->update_attributes($attr);

       		if(!$ticket){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_assign_queue_error'));}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_assign_queue_success'));}
			redirect('tickets/view/'.$id);
		}else
		{
			$this->view_data['queues'] = Queue::find('all',array('conditions' => array('inactive=?','0')));
			$this->view_data['ticket'] = Ticket::find_by_id($id);
			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_queue');
			$this->view_data['form_action'] = 'tickets/queue';
			$this->content_view = 'tickets/_queue';
		}
	}
	function type($id = FALSE)
	{
		$this->load->helper('notification');
		if($_POST){
			unset($_POST['send']);
			unset($_POST['_wysihtml5_mode']);
			unset($_POST['files']);
			$id = $_POST['id'];
			unset($_POST['id']);
			$ticket = Ticket::find_by_id($id);
			$attr = array('type_id' => $_POST['type_id']);
			$ticket->update_attributes($attr);

       		if(!$ticket){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_assign_type_error'));}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_assign_type_success'));}
			redirect('tickets/view/'.$id);
		}else
		{
			$this->view_data['types'] = Type::find('all',array('conditions' => array('inactive=?','0')));
			$this->view_data['ticket'] = Ticket::find_by_id($id);
			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_type');
			$this->view_data['form_action'] = 'tickets/type';
			$this->content_view = 'tickets/_type';
		}
	}
	function status($id = FALSE)
	{
		$this->load->helper('notification');
		if($_POST){
			unset($_POST['send']);
			unset($_POST['_wysihtml5_mode']);
			unset($_POST['files']);
			$id = $_POST['id'];
			unset($_POST['id']);
			$ticket = Ticket::find_by_id($id);
			$attr = array('status' => $_POST['status']);
			$ticket->update_attributes($attr);

       		if(!$ticket){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_status_error'));}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_status_success'));}
			redirect('tickets/view/'.$id);
		}else
		{

			$this->view_data['ticket'] = Ticket::find_by_id($id);
			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_status');
			$this->view_data['form_action'] = 'tickets/status';
			$this->content_view = 'tickets/_status';
		}
	}
	function close($id = FALSE)
	{
		$this->load->helper('notification');
		if($_POST){
			unset($_POST['send']);
			unset($_POST['_wysihtml5_mode']);
			unset($_POST['files']);
			$id = $_POST['ticket_id'];
			unset($_POST['ticket_id']);
			$ticket = Ticket::find_by_id($id);
			$attr = array('status' => "closed");
			$ticket->update_attributes($attr);
			if(isset($ticket->client->email)){ $email = $ticket->client->email; } else {$emailex = explode(' - ', $ticket->from); $email = $emailex[1]; }
			if(isset($_POST['notify'])){

			send_ticket_notification($email, '[Ticket#'.$ticket->reference.'] - '.$ticket->subject, $_POST['message'], $ticket->id);
			}
			send_ticket_notification($ticket->user->email, '[Ticket#'.$ticket->reference.'] - '.$ticket->subject, $_POST['message'], $ticket->id);
			$_POST['internal'] = "0";
			unset($_POST['notify']);
			$_POST['subject'] = htmlspecialchars($_POST['subject']);
			$_POST['datetime'] = time();
			$_POST['from'] = $this->user->firstname." ".$this->user->lastname.' - '.$this->user->email;
			$_POST['reply_to'] = $this->user->email;
			$_POST['ticket_id'] = $id;
			$_POST['to'] = $email;
			unset($_POST['client_id']);
			$article = TicketHasArticle::create($_POST);
       		if(!$ticket){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_save_ticket_error'));}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_ticket_close_success'));}
			redirect('tickets');
		}else
		{
			$this->view_data['ticket'] = Ticket::find($id);
			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_close');
			$this->view_data['form_action'] = 'tickets/close';
			$this->content_view = 'tickets/_close';
		}
	}
	function view($id = FALSE, $article_guid = FALSE)
	{
		$this->view_data['submenu'] = array();
		$this->content_view = 'tickets/view';
		$this->view_data['ticket'] = Ticket::find_by_id($id);

		if($this->user->admin == 0){
			$comp_array = array();
			foreach ($this->user->companies as $value) {
				array_push($comp_array, $value->id);
			}
			if(!in_array($this->view_data['ticket']->company_id, $comp_array) && $this->user->queue != $this->view_data['ticket']->queue_id){
				redirect('tickets');
			}

		}

		if($this->view_data['ticket']->status == "new"){
			$this->view_data['ticket']->status = "open";
			$this->view_data['ticket']->save();
		}
		if(isset($this->view_data['ticket']->user->id)){ $ticket_id = $this->view_data['ticket']->user->id;}else{ $ticket_id = "0"; }
		if($this->view_data['ticket']->updated == "1" AND $ticket_id == $this->user->id){
			$this->view_data['ticket']->updated = "0";
			$this->view_data['ticket']->save();
		}
		$this->view_data['form_action'] = 'tickets/article/'.$id.'/add';
		$this->view_data['article_id'] = '-1';

		if($article_guid) {
			$mention = Mention::find( 'all', array( 'conditions' => array( 'guid=?', $article_guid ) ) );

			if(isset($mention[0]))
				$this->view_data['article_id'] = $mention[0]->item_id;
		}

		if(!$this->view_data['ticket']){redirect('tickets');}
	}
	function article($id = FALSE, $condition = FALSE, $article_id = FALSE)
	{
		$this->view_data['submenu'] = array(
								$this->lang->line('application_back') => 'tickets',
								$this->lang->line('application_overview') => 'tickets/view/'.$id,
						 		);
		switch ($condition) {
			case 'add':
				$this->content_view = 'tickets/_note';
				if($_POST){
					$config['upload_path'] = './files/media/';
					$config['encrypt_name'] = TRUE;
					$config['allowed_types'] = '*';

					$this->load->library('upload', $config);
					$this->load->helper('notification');
					$this->load->helper('mention');

					unset($_POST['userfile']);
					unset($_POST['file-name']);

					unset($_POST['send']);
					unset($_POST['_wysihtml5_mode']);
					unset($_POST['files']);
					$ticket = Ticket::find($id);
					if(isset($_POST['internal'])){
						$notify = "true";
						$_POST['internal'] = "0";
					}else{
						$_POST['internal'] = "1";
					}
					$_POST['subject'] = htmlspecialchars($_POST['subject']);
					$_POST['user_id'] = $this->user->id;
					$_POST['datetime'] = time();
					$_POST['from'] = $this->user->firstname." ".$this->user->lastname.' - '.$this->user->email;
					$_POST['reply_to'] = $this->user->email;

					$article = TicketHasArticle::create($_POST);

					if($mention_users = has_mentioned($_POST['message'], $this->user->id)) {
						$mention_guid = generate_mention_guid();
						$mention_action = "/tickets/view/{$id}/{$mention_guid}";

                        foreach ($mention_users as $mention_user) {
                            $mention = Mention::create([
                                'guid' => $mention_guid,
                                'mention' => $mention_user->username,
                                'comment' => $_POST['message'],
                                'action' => $mention_action,
                                'user_id' => $this->user->id,
                                'item_id' => $article->id,
                                'is_read' => false
                            ]);

                            if ($mention->guid) {
                                $mention_action = base_url($mention_action);

                                $mention_email_body = $_POST['message'];
                                $mention_email_body .= "<a href='{$mention_action}'>{$this->lang->line('application_view_ticket')}</a>";

                                send_user_notification($this->user, $mention_user->email, 'Mention', $mention_email_body, false, 'tickets/view/' . $id);
                            }
                        }
					}

					$email_attachment = "";
					if ( ! $this->upload->do_upload())
						{
							$error = $this->upload->display_errors('', ' ');
							$this->session->set_flashdata('message', 'error:'.$error);

						}
						else
						{
							$data = array('upload_data' => $this->upload->data());

							$attributes = array('article_id' => $article->id, 'filename' => $data['upload_data']['orig_name'], 'savename' => $data['upload_data']['file_name']);
							$attachment = ArticleHasAttachment::create($attributes);
							$email_attachment = "files/media/".$data['upload_data']['file_name'];
						}
					if (isset($notify)) {
						if(isset($ticket->client->email)){$to = $ticket->client->email;}else{$emailex = explode(' - ', $ticket->from); $to = $emailex[1];}
						send_ticket_notification($to, '[Ticket#'.$ticket->reference.'] - '.$_POST['subject'], $_POST['message'], $ticket->id, $email_attachment);

					}


		       		if(!$article){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_save_article_error'));}
		       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_save_article_success').$mention_email_body);}
					redirect('tickets/view/'.$id);
				}else
				{
					$this->theme_view = 'modal';
					$this->view_data['ticket'] = Ticket::find($id);
					$this->view_data['title'] = $this->lang->line('application_add_note');
					$this->view_data['form_action'] = 'tickets/article/'.$id.'/add';
					$this->content_view = 'tickets/_note';
				}
				break;

			default:
				redirect('tickets');
				break;
		}

	}

	function comment( $id = false )
	{
		$ticket = Ticket::find( $id );
		if ( isset( $_POST['internal'] ) ) {
			$notify            = "true";
			$_POST['internal'] = "0";
		} else {
			$_POST['internal'] = "1";
		}
		$_POST['message']  = htmlspecialchars( $_POST['message'] );
		$_POST['user_id']  = $this->user->id;
		$_POST['datetime'] = time();
		$_POST['ticket_id'] = $id;
		$_POST['from']     = $this->user->firstname . " " . $this->user->lastname . ' - ' . $this->user->email;
		$_POST['reply_to'] = $this->user->email;

		$article     = TicketHasArticle::create( $_POST );
		$new_article = $article->attributes();

		$new_article['message'] = strip_tags($article->message);
		$new_article['datetime'] = date("Y-m-d", $article->datetime);

		if($article->user)
			$new_article['user_info'] = $article->user->attributes();

		echo json_encode( [
			                  'status'  => true,
			                  'article' => $new_article
		                  ] );
		die();
	}
	function bulk($action)
	{
		$this->load->helper('notification');
		if($_POST){
			if(empty($_POST['list'])){redirect('tickets');}
			$list = explode(",", $_POST['list']);

			switch ($action) {
				case 'close':
					$attr = array('status' => "closed");
					$email_message = $this->lang->line('messages_bulk_ticket_closed');
					$success_message = $this->lang->line('messages_bulk_ticket_closed_success');
					break;

				default:
					redirect('tickets');
				break;
			}

			foreach ($list as $value) {
				$ticket = Ticket::find_by_id($value);
				$ticket->update_attributes($attr);
				send_ticket_notification($ticket->user->email, '[Ticket#'.$ticket->reference.'] - '.$ticket->subject, $email_message, $ticket->id);
				if(!$ticket){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_save_ticket_error'));}
       			else{$this->session->set_flashdata('message', 'success:'.$success_message);}

			}
			redirect('tickets');

			/*
			if(isset($ticket->client->email)){ $email = $ticket->client->email; } else {$emailex = explode(' - ', $ticket->from); $email = $emailex[1]; }
			if(isset($_POST['notify'])){
				
			send_ticket_notification($email, '[Ticket#'.$ticket->reference.'] - '.$ticket->subject, $_POST['message'], $ticket->id);
			}
			send_ticket_notification($ticket->user->email, '[Ticket#'.$ticket->reference.'] - '.$ticket->subject, $_POST['message'], $ticket->id);
			$_POST['internal'] = "0";
			unset($_POST['notify']);
			$_POST['subject'] = htmlspecialchars($_POST['subject']);
			$_POST['datetime'] = time();
			$_POST['from'] = $this->user->firstname." ".$this->user->lastname.' - '.$this->user->email;
			$_POST['reply_to'] = $this->user->email;
			$_POST['ticket_id'] = $id;
			$_POST['to'] = $email;
			unset($_POST['client_id']);
			$article = TicketHasArticle::create($_POST);
       		if(!$ticket){$this->session->set_flashdata('message', 'error:'.$this->lang->line('messages_save_ticket_error'));}
       		else{$this->session->set_flashdata('message', 'success:'.$this->lang->line('messages_ticket_close_success'));}
			redirect('tickets');
			*/
		}else
		{
			$this->view_data['ticket'] = Ticket::find($id);
			$this->theme_view = 'modal';
			$this->view_data['title'] = $this->lang->line('application_close');
			$this->view_data['form_action'] = 'tickets/close';
			$this->content_view = 'tickets/_close';
		}
	}

	function attachment($id = FALSE){
		$this->load->helper('file');
		$this->load->helper('download');
		$attachment = TicketHasAttachment::find_by_savename($id);

		$file = './files/media/'.$attachment->savename;
		$mime = get_mime_by_extension($file);
		if(file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: '.$mime);
            header('Content-Disposition: attachment; filename='.basename($attachment->filename));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            ob_clean();
            flush();
            exit;
        }
	}
	function articleattachment($id = FALSE){
		$this->load->helper('download');
		$this->load->helper('file');

		$attachment = ArticleHasAttachment::find_by_savename($id);
		$file = './files/media/'.$attachment->savename;
		$mime = get_mime_by_extension($file);
		if(file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: '.$mime);
            header('Content-Disposition: attachment; filename='.basename($attachment->filename));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            ob_clean();
            flush();
            exit;
        }
	}

}