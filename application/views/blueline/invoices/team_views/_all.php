	<div class="col-sm-12  col-md-12 main">  
    <div class="row tile-row">
      <div class="col-md-3 col-xs-6 tile"><div class="icon-frame hidden-xs"><i class="ion-ios-bell"></i> </div><h1> <?php if(isset($invoices_due_this_month)){echo $invoices_due_this_month;} ?> <span><?=$this->lang->line('application_invoices');?></span></h1><h2><?=$this->lang->line('application_due_this_month');?></h2></div>
      <div class="col-md-3 col-xs-6 tile"><div class="icon-frame secondary hidden-xs"><i class="ion-ios-analytics"></i> </div><h1> <?php if(isset($invoices_paid_this_month)){echo $invoices_paid_this_month;} ?> <span><?=$this->lang->line('application_invoices');?></span></h1><h2><?=$this->lang->line('application_paid_this_month');?></h2></div>
      <div class="col-md-6 col-xs-12 tile hidden-xs">
      <div style="width:97%; margin-top: -4px; margin-bottom: 17px; height: 80px;">
            <canvas id="tileChart" width="auto" height="80"></canvas>
        </div>
      </div>
    
    </div>   
     <div class="row">
         <?php if ($user->admin != 1) : ?>

             <a href="<?=base_url()?>tinvoices/create" class="btn btn-primary" data-toggle="mainmodal"><?=$this->lang->line('application_create_invoice');?></a>
            <?php if (!$isSignedUp) :?>
                <a href="<?=base_url()?>tinvoices/propay_signup" class="btn btn-primary" data-toggle="mainmodal">Signup Merchant Account</a>
            <?php endif; ?>
         <?php endif; ?>
      <div class="btn-group pull-right-responsive margin-right-3">
          <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
            <?php $last_uri = $this->uri->segment($this->uri->total_segments()); if($last_uri != "tinvoices"){echo $this->lang->line('application_'.$last_uri);}else{echo $this->lang->line('application_all');} ?> <span class="caret"></span>
          </button>
          <ul class="dropdown-menu pull-right" role="menu">
            <?php foreach ($submenu as $name=>$value):?>
	                <li><a id="<?php $val_id = explode("/", $value); if(!is_numeric(end($val_id))){echo end($val_id);}else{$num = count($val_id)-2; echo $val_id[$num];} ?>" href="<?=site_url($value);?>"><?=$name?></a></li>
	            <?php endforeach;?>
          </ul>
      </div>
    </div>  
      <div class="row">

         <div class="table-head"><?=$this->lang->line('application_invoices');?></div>
         <div class="table-div">
		<table class="data table" id="tinvoices" rel="<?=base_url()?>" cellspacing="0" cellpadding="0">
		<thead>
			<th width="70px" class="hidden-xs"><?=$this->lang->line('application_invoice_id');?></th>
			<th ><?=$this->lang->line('application_client');?></th>
			<th class="hidden-xs"><?=$this->lang->line('application_issue_date');?></th>
			<th class="hidden-xs"><?=$this->lang->line('application_due_date');?></th>
      <th class="hidden-xs"><?=$this->lang->line('application_value');?></th>
			<th><?=$this->lang->line('application_status');?></th>
			<th><?=$this->lang->line('application_action');?></th>
		</thead>
		<?php foreach ($invoices as $invoice):?>

		<tr id="<?=$invoice->id;?>" >
			<td class="hidden-xs"><?=$core_settings->invoice_prefix;?><?=$invoice->reference;?></td>
			<td><span class="label label-info"><?php if(is_object($invoice->company)){echo $invoice->company->name; }?></span></td>
			<td class="hidden-xs"><span><?php $unix = human_to_unix($invoice->issue_date.' 00:00'); echo '<span class="hidden">'.$unix.'</span> '; echo date($core_settings->date_format, $unix);?></span></td>
			<td class="hidden-xs">
        <span class="label <?php if($invoice->status == "Paid"){echo 'label-success';} if($invoice->due_date <= date('Y-m-d') && $invoice->status != "Paid" && $invoice->status != "Canceled"){ echo 'label-important tt" title="'.$this->lang->line('application_overdue'); } ?>"><?php $unix = human_to_unix($invoice->due_date.' 00:00'); echo '<span class="hidden">'.$unix.'</span> '; echo date($core_settings->date_format, $unix);?></span> <span class="hidden"><?=$unix;?></span></td>
			<td class="hidden-xs"><?php if(isset($invoice->sum)){echo display_money($invoice->sum, $invoice->currency);} ?> </td>
      <td class="<?=$invoice->status?>">
        <div class="dropdown">
          <span class="label dropdown-toggle <?php $unix = human_to_unix($invoice->sent_date.' 00:00');
              if($invoice->status == "Paid"){echo 'label-success';}elseif($invoice->status == "Sent"){ echo 'label-warning tt" title="'.date($core_settings->date_format, $unix);} ?>"
                data-toggle="dropdown"
          >
                <span><?=$this->lang->line('application_'.$invoice->status);?></span> <i class="ion-chevron-down visible-on-hover"></i>
          </span>
          <ul class="quick-change-list dropdown-menu">
           <li>
                <a href="<?=base_url()?>tinvoices/changestatus/<?=$invoice->id;?>/Open" class="ajax-silent label-changer" data-status="Open" >
                  <?=$this->lang->line('application_Open');?>
                </a>
           </li>
          <li>
                <a href="<?=base_url()?>tinvoices/changestatus/<?=$invoice->id;?>/Sent" class="ajax-silent label-changer" data-status="Sent">
                  <?=$this->lang->line('application_Sent');?>
                </a>
           </li>
           <li>
                <a href="<?=base_url()?>tinvoices/changestatus/<?=$invoice->id;?>/Paid" class="ajax-silent label-changer" data-status="Paid">
                  <?=$this->lang->line('application_Paid');?>
                </a>
           </li>
           <li>
                <a href="<?=base_url()?>tinvoices/changestatus/<?=$invoice->id;?>/PartiallyPaid" class="ajax-silent label-changer" data-status="PartiallyPaid">
                  <?=$this->lang->line('application_PartiallyPaid');?>
                </a>
           </li>
           <li>
                <a href="<?=base_url()?>tinvoices/changestatus/<?=$invoice->id;?>/Canceled" class="ajax-silent label-changer" data-status="Canceled">
                  <?=$this->lang->line('application_Canceled');?>
                </a>
           </li>
          </ul>   
          </div>
      </td>
		
			<td class="option" width="8%">
				        <button type="button" class="btn-option delete po" data-toggle="popover" data-placement="left" data-content="<a class='btn btn-danger po-delete ajax-silent' href='<?=base_url()?>tinvoices/delete/<?=$invoice->id;?>'><?=$this->lang->line('application_yes_im_sure');?></a> <button class='btn po-close'><?=$this->lang->line('application_no');?></button> <input type='hidden' name='td-id' class='id' value='<?=$invoice->id;?>'>" data-original-title="<b><?=$this->lang->line('application_really_delete');?></b>"><i class="icon dripicons-cross"></i></button>
				        <a href="<?=base_url()?>tinvoices/update/<?=$invoice->id;?>" class="btn-option" data-toggle="mainmodal"><i class="icon dripicons-gear"></i></a>
			</td>
		</tr>

		<?php endforeach;?>
	 	</table>
            </div>

      </div>

<script>
$(document).ready(function(){ 


//chartjs

<?php
                                $days = array(); 
                                $data = "";
                                $data2 = "";
                                $this_week_days = array(
                                  date("Y-m-d",strtotime('monday this week')),
                                  date("Y-m-d",strtotime('tuesday this week')),
                                    date("Y-m-d",strtotime('wednesday this week')),
                                      date("Y-m-d",strtotime('thursday this week')),
                                        date("Y-m-d",strtotime('friday this week')),
                                          date("Y-m-d",strtotime('saturday this week')),
                                            date("Y-m-d",strtotime('sunday this week')));

                                $labels = '';
                                
                                //First Dataset            
                                foreach ($invoices_paid_this_month_graph as $paid_invoice) {
                                  $days[$paid_invoice->date_formatted] = $paid_invoice->amount;
                                }
                                foreach ($this_week_days as $selected_day) {
                                  $y = 0;
                                  $labels .= '"'.$selected_day.'",';
                                    if(isset($days[$selected_day])){ $y = $days[$selected_day];}
                                      $data .= $y.",";
                                      $selday = $selected_day;
                                     } 

                                //Second Dataset
                                foreach ($invoices_due_this_month_graph as $paid_invoice) {
                                  $days[$paid_invoice->date_formatted] = $paid_invoice->amount;
                                }
                                foreach ($this_week_days as $selected_day2) {
                                  $y = 0;
                                    if(isset($days[$selected_day2])){ $y = $days[$selected_day2];}
                                      $data2 .= $y.",";
                                      $selday2 = $selected_day2;
                                     }
                                     ?>


var ctx = document.getElementById("tileChart").getContext("2d");
    var myBarChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: [<?=$labels?>],
        datasets: [
        {
          label: "<?=$this->lang->line('application_paid');?>",
          backgroundColor: "rgba(51, 195, 218, 0.3)",
          borderColor: "rgba(51, 195, 218, 1)",
          pointBorderColor: "rgba(51, 195, 218, 0)",
          pointBackgroundColor: "rgba(51, 195, 218, 1)",
          pointHoverBackgroundColor: "rgba(51, 195, 218, 1)",
          pointHitRadius: 25,
          pointRadius: 2,
          borderWidth: 2,
          data: [<?=$data;?>]
        }]
      },
       options: {
         title: {
            display: true,
            text: ' '
        },
        maintainAspectRatio: false,
        tooltips:{
          enabled: true,
        },
        legend:{
          display: false
        },
        scales: {
          yAxes: [{
            gridLines: { 
                        display: false, 
                        lineWidth: 2,
                        color: "rgba(51, 195, 218, 0)"
                      },
            ticks: {
                        beginAtZero:true,
                        display: false,
                    }
          }],
          xAxes: [{
             gridLines: { 
                        display: false, 
                        lineWidth: 2,
                        color: "rgba(51, 195, 218, 0)"
                      },
            ticks: {
                        beginAtZero:true,
                        display: false,
                    }
          }]
        }
      }

    });



});
</script>