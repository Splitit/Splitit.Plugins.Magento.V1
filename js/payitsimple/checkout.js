jQuery(document).ready(function(){

	var curUrl      = window.location.href; 
	var baseUrl = curUrl.substring(0, curUrl.indexOf('checkout'));
	var samePayment = "";
	var numOfInstallmentsResponse = 0;

	jQuery(document).on("click", "#payment-schedule-link", function(){
		jQuery("#approval-popup").addClass("overflowHidden");
		jQuery('#payment-schedule, ._popup_overlay').show();
	});
	jQuery(document).on("click", "#complete-payment-schedule-close", function(){
		jQuery("#approval-popup").removeClass("overflowHidden");
		jQuery('#payment-schedule, ._popup_overlay').hide();	
	});

	jQuery(document).on("click", "#i_acknowledge_content_show", function(){
		jQuery("#approval-popup").addClass("overflowHidden");
		jQuery('#termAndConditionpopup, ._popup_overlay').show();		
	});

	jQuery(document).on("click", "#termAndConditionpopupCloseBtn", function(){
		jQuery("#approval-popup").removeClass("overflowHidden");
		jQuery('#termAndConditionpopup, ._popup_overlay').hide();	
	});


	// get number of installment when splitit payment gateway already selected
    if (jQuery('dt#dt_method_pis_cc input#p_method_pis_cc').is(':checked')) {
    	getNumOfInstallments();
    }

    // hide I acknowdge
    jQuery(document).on("click","#i_acknowledge",function(){
    	if(jQuery('#i_acknowledge').is(":checked")){
	    	jQuery(".i_ack_err").hide();
	    }else{
	    	jQuery(".i_ack_err").show();
	    }
    });
    

	// get number of installment on click of splitit payment gateway	
	/*jQuery(document).on("click","dt#dt_method_pis_cc input#p_method_pis_cc", function() {
		alert("sdfsfdf");
		getNumOfInstallments();
    	
    });*/

    jQuery(document).on('click','#checkout-payment-method-load input[type="radio"]',function(e){
    	if(jQuery(this).attr("id") == 'p_method_pis_cc'){
    		jQuery("#payment-buttons-container button").attr("onclick","");
    		getNumOfInstallments();
    	}
    	else{
    		jQuery("#payment-buttons-container button").attr("onclick","payment.save();");
    	}
    })
	var isAlreadyClickInFormFields = 0;
	jQuery(document).on("click","#payment_form_pis_cc li", function() {
		

		if(isAlreadyClickInFormFields == 0){
			jQuery("#payment-buttons-container button").attr("onclick","");
			getNumOfInstallments();	
		}
		isAlreadyClickInFormFields = 1;
		
    	
    });


    
    /*jQuery("#payment_form_pis_cc li").on("click",function(){
    	alert("sdfsfd");
    });*/

	function getNumOfInstallments(){
		var selectedInstallment = jQuery("#pis_cc_installments_no").val();
		
		jQuery.ajax({
	        url : baseUrl+"payitsimple/payment/apiLogin/",
	        type : 'POST',
	        dataType:'json',
	        data:{},
	        success : function(obj){	        	
	        	
	            if (obj.status == true) {
	               /*var html = '<option value="" class="number-of-installments">--Please Select--</option>';
	               jQuery.each( obj.data, function( index, value ){
					    html +=  '<option value="'+index+'">'+value+'</option>';
					});
	               jQuery("#pis_cc_installments_no").html(html);*/
	               /*if(obj.installmentNum == 0){
	               		jQuery("#pis_cc_installments_no option:nth-child(2)").attr("value", "");
	               }*/
	               numOfInstallmentsResponse = 1;
	            }else {
	            	alert(obj.error);
	             //showAjaxMessage(test.message,"danger");
	            }
	            samePayment = jQuery("#payment-buttons-container button").attr("onclick");
	            jQuery("#payment-buttons-container button").attr("onclick","");

	        },
	        async:false
	    });
	}
    // Run when user click on continue button after filling all Credit card details
    
    jQuery("#payment-buttons-container button").click(function(){
    	 if(jQuery('#p_method_pis_cc').is(':checked')){


	    	 var paymentForm = new VarienForm('co-payment-form');
	    	 jQuery("#payment-buttons-container button").attr("onclick","");// remove onlick attribute on continue button of payment section
	    	 if(paymentForm.validator.validate()){ // validate payment form
	    	 	//var checkNewDropDown = jQuery(".number-of-installments").length;
		    	jQuery("#payment-please-wait").show();// show loader in payment section

		    	if(numOfInstallmentsResponse == 0 /*||  checkNewDropDown == 0*/){
		    		getNumOfInstallments();
		    	}

		    	var selectedInstallment = jQuery("#pis_cc_installments_no").val();
		    	if(selectedInstallment == ""){
		    		jQuery("#payment-please-wait").hide();
		    		alert("Please select Number of Installments");
		    		return;
		    	}
		    	jQuery.ajax({
			        url : baseUrl+"payitsimple/payment/installmentplaninit/",
			        type : 'POST',
			        dataType:'json',
			        data:{"selectedInstallment":selectedInstallment},
			        success : function(obj){	        	
			        	
			            if (obj.status == true) {
			            	jQuery("#approval-popup").remove();
			            	jQuery('body').append(obj.data);
			            	

			            }else {
			            	alert(obj.data);
			             
			            }
			            jQuery("#payment-please-wait").hide();
			            

			        }
			    });
	    	 }else{
				return;
	    	 }
    	}else{
    		jQuery("#payment-buttons-container button").attr("onclick","payment.save()");
    	}

    	
    });

    
    

});
// close splitit popup when user check I agree
function paymentSave(){
    if(jQuery('#i_acknowledge').is(":checked")){
    	jQuery(".approval-popup_ovelay").hide();
		eval(payment.save());
		jQuery("#approval-popup").hide();	
    }else{
    	jQuery(".i_ack_err").show();
    }	
	

}

// close Approval popup
function closeApprovalPopup(){
	jQuery("#approval-popup, .approval-popup_ovelay").hide();
}

// function showPaymentSchedule{

// }



