
// Hi I am in feature 2


var dealer_count = dealer_list.length; 

var global_count_month = new Array();
var global_count_inbound = new Array();
var global_count_outbound = new Array();

var total_incoming = 0;
var total_outgoing = 0;
var avg_total_incoming = 0;
var avg_total_outgoing = 0;
var max_dealer = '';
var max_sent = 0;
var zero_dealer = '';

for(var m=0; m<12;m++)
{
		global_count_inbound[m] = 0;
		global_count_outbound[m] = 0;
}
var global_count = new Array();

var $r = 0;

for(var k=0; k<dealer_count; k++)
{
	var process = k + 1;

	document.getElementById("database_count").innerHTML = '';
	document.getElementById("database_count").innerHTML = "Processing Dealer "+process + " out of " + dealer_count;

	var dealer = dealer_list[k];

	  if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp=new XMLHttpRequest();
	  } else { // code for IE6, IE5
		xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	  }
	  xmlhttp.onreadystatechange=function() {
		if (xmlhttp.readyState==4 && xmlhttp.status==200) {
		
		  var resp = JSON.parse(xmlhttp.responseText);
		  
		  if(resp != 'Twilio Setting are not found for dealer'){
		  var res_html1 = '</br></br>********** Final Results *************</br></br>'; 
		  var res_html = '</br></br>************<b>'+dealer+' </b>*************</br></br>';
		  var rt = 0;
		  
		  var total_incoming_dealer = 0;
		  var total_outgoing_dealer = 0;
		  var avg_incoming_dealer = 0;
		  var avg_outgoing_dealer = 0;
		  var total_months_dealer = resp.length;

		  for(var t=0; t<resp.length;t++)
		  {
			/*global_count_month    =  resp[t].month;
			global_count_inbound  =  resp[t].total_number_messages_inbound;
			global_count_outbound =  resp[t].total_number_messages_outbound;
			*/
			
			total_incoming = total_incoming + parseInt(resp[t].total_number_messages_inbound);
			
			total_outgoing = total_outgoing + parseInt(resp[t].total_number_messages_outbound);
			
			total_incoming_dealer = total_incoming_dealer + parseInt(resp[t].total_number_messages_inbound);
			
			total_outgoing_dealer = total_outgoing_dealer + parseInt(resp[t].total_number_messages_outbound);
			
			global_count_month[rt] =  resp[t].month;
			global_count_inbound[rt] = global_count_inbound[rt] + parseInt(resp[t].total_number_messages_inbound);
			global_count_outbound[rt] = global_count_outbound[rt] + parseInt(resp[t].total_number_messages_outbound);
			
			
			var total_messages = resp[t].total_number_messages_inbound + resp[t].total_number_messages_outbound;
			
			res_html = res_html + resp[t].month+": "+ total_messages.toLocaleString() + "</br>";
			rt = rt + 1;
		  }
		  
		  avg_incoming_dealer = total_incoming_dealer / total_months_dealer;
		  avg_outgoing_dealer = total_outgoing_dealer / total_months_dealer;
		  res_html = res_html +"</br></br>"+ "Avg Outgoing: "+avg_outgoing_dealer.toLocaleString()
		  + "</br>"
		  + "Avg Incoming: "+avg_incoming_dealer.toLocaleString()
		  + "</br> -----------------------------------------------";
		  var total_sms = total_incoming_dealer + total_outgoing_dealer;
		  if(total_sms == 0)
		  {
			zero_dealer = zero_dealer + dealer + ", ";
		  }
		  
		 
		  if(total_outgoing_dealer > max_sent)
		  {
			max_sent = total_outgoing_dealer;
			max_dealer = dealer;
		  }
		 
		  //console.log("inbound: "+resp[0].total_number_messages_inbound);
		  
		 // console.log(JSON.parse(xmlhttp.responseText['total_number_messages_inbound']));
		  var innHTML = document.getElementById("txt").innerHTML;
		  innHTML = innHTML + res_html;
		  document.getElementById("txt").innerHTML = innHTML;
		  
		  var html = '</br></br>Final Result is </br></br>';
		  
		  html = html + 'Total Incoming = '+total_incoming.toLocaleString()+' </br>';
		  html = html + 'Total Outgoing = '+total_outgoing.toLocaleString()+' </br></br>';
		  
		  var avg_total_incoming = total_incoming / process;
		  var avg_total_outgoing = total_outgoing / process;
		  
		  html = html + 'Avg Incoming = '+avg_total_incoming.toLocaleString()+' </br>';
		  html = html + 'Avg Outgoing = '+avg_total_outgoing.toLocaleString()+' </br></br>';
		  
		  // console.log(global_count_month);
		  // console.log(global_count_inbound);
		  // console.log(global_count_outbound);
		  for(var y=0; y<global_count_month.length; y++)
		  {
			var gcount = global_count_inbound[y] + global_count_outbound[y];
			html = html + global_count_month[y]+": "+gcount.toLocaleString()+"</br>";
		  }
		  
		  
		  html = html + "</br></br>Dealership that used it the most: "+ max_dealer + "(had "+max_sent.toLocaleString()+ " total message sent for the year)</br></br>";
		  
		  html = html + "List of stores that have not sent out or received a text in the Last 12 months:</br>"+zero_dealer;
		  
		  var innHTML1 = '';
		  innHTML1 = innHTML1 + html;
		  document.getElementById("totaltxt").innerHTML = innHTML1;
		  }
		  else{
				var res_html = '</br></br>********** '+dealer+'*************</br></br>';
				var innHTML = document.getElementById("txt").innerHTML;
				innHTML = innHTML + res_html + resp + " "+ dealer;
				document.getElementById("txt").innerHTML = innHTML;
		  }
		}
	  }
	  xmlhttp.open("GET","twilio_ajax.php?dealer="+dealer,false);
	  xmlhttp.send();
}



document.getElementById("database_count").innerHTML = '';
document.getElementById("database_count").innerHTML = "All Dealers have been Processed!";
