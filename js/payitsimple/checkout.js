var numOfInstallmentsResponse = 0;
var isLogedIn = 0;
var isLoging = 0;
var curUrl = window.location.href;
var baseUrl = "";
function getBaseUrl() {
    baseUrl = jQuery(".redirect-class").attr("data-baseurl");
    if (typeof baseUrl === 'undefined') {
        baseUrl = window.location.origin + '/';
    }
    return baseUrl;
}
jQuery(document).ready(function () {
    setTimeout(function () {
        if (jQuery(document).find('#p_method_pis_cc:checked').val()) {
            jQuery(document).find("#checkout-review-submit").find('button').addClass("disabled");
        }
        ;
    }, 3000);

    jQuery(document).find("#checkout-review-submit").find('button').click(function () {
        if (jQuery('#iaprove').is(':checked')) {
            jQuery('#i_acknowledge').prop('checked', true);
            jQuery('#pis_cc_terms').prop('checked', true);
            jQuery('#one-step-checkout-review-terms-agreement-mc_osc_term').prop('checked', true);
        }
    });
    jQuery('body').click(function (e) {
        if (e.srcElement === undefined) {
            return true;
        }
        if ('#' + e.srcElement.id == '#p_method_pis_cc') {
            jQuery(document).find("#checkout-review-submit").find('button').addClass("disabled");
        }

        if ('#' + e.srcElement.id == '#iaprove') {
            if (jQuery('#iaprove').is(':checked')) {
                var res = installmentPlanInit(true);
                if (res) {
                    jQuery(document).find("#checkout-review-submit").find('button').removeClass("disabled");
                    jQuery('#i_acknowledge').prop('checked', true);
                    jQuery('#pis_cc_terms').prop('checked', true);
                    jQuery('#one-step-checkout-review-terms-agreement-mc_osc_term').prop('checked', true);
                } else {
                    jQuery('#iaprove').prop('checked', false);
                }
            } else {
                if (!jQuery(document).find("#checkout-review-submit").find('button').hasClass("disabled")) {
                    jQuery(document).find("#checkout-review-submit").find('button').addClass("disabled");
                }
            }
        }

    });


    //tell me more button
    jQuery(document).on('click', '#tell-me-more', function (e) {

        e.preventDefault();
        var left = (screen.width - 433) / 2;
        var top = (screen.height / 2) - (window.innerHeight / 2);
        var win = window.open(jQuery(this).attr('href'), "Tell me more", "width=433,height=607,left=" + left + ",top=" + top + ",location=no,status=no,scrollbars=no,resizable=no");
        win.document.writeln("<body style='margin:0px'><img width=100% src='" + jQuery(this).attr('href') + "' />");
        win.document.writeln("</body>");
        win.document.write('<title>Splitit Learn More</title>');

        return;
    });

    var samePayment = "";

    //baseUrl = jQuery("#payment-img").attr("data-baseurl");
    getBaseUrl();


    jQuery(document).on("click", "#payment-schedule-link", function () {
        jQuery("#approval-popup").addClass("overflowHidden");
        jQuery('#payment-schedule, ._popup_overlay').show();
    });
    jQuery(document).on("click", "#complete-payment-schedule-close", function () {
        jQuery("#approval-popup").removeClass("overflowHidden");
        jQuery('#payment-schedule, ._popup_overlay').hide();
    });

    jQuery(document).on("click", "#i_acknowledge_content_show", function () {
        jQuery("#approval-popup").addClass("overflowHidden");
        jQuery('#termAndConditionpopup, ._popup_overlay').show();
    });

    jQuery(document).on("click", "#termAndConditionpopupCloseBtn", function () {
        jQuery("#approval-popup").removeClass("overflowHidden");
        jQuery('#termAndConditionpopup, ._popup_overlay').hide();
    });


    // get number of installment when splitit payment gateway already selected
    if (jQuery('dt#dt_method_pis_cc input#p_method_pis_cc').is(':checked')) {
        getNumOfInstallments();
    }

    // hide I acknowdge
    jQuery(document).on("click", "#i_acknowledge", function () {
        if (jQuery('#i_acknowledge').is(":checked")) {
            jQuery(".i_ack_err").hide();
        } else {
            jQuery(".i_ack_err").show();
        }
    });


    // get number of installment on click of splitit payment gateway	
    /*jQuery(document).on("click","dt#dt_method_pis_cc input#p_method_pis_cc", function() {
     alert("sdfsfdf");
     getNumOfInstallments();
     
     });*/

    jQuery(document).on('click', '#checkout-payment-method-load input[type="radio"]', function (e) {
        if (jQuery(this).attr("id") == 'p_method_pis_cc') {
            //jQuery("#payment-buttons-container button").attr("onclick","");
            getNumOfInstallments();
        } else {
            // check if hosted solution is selected as payment mode
            /*jQuery("#payment-buttons-container button").show();
             jQuery(document).find("#payment-buttons-container .splitit-checkout-url").remove();*/
            //jQuery("#payment-buttons-container button").attr("onclick","payment.save();");
        }
    })
    var isAlreadyClickInFormFields = 0;
    jQuery(document).on("click", "#payment_form_pis_cc li", function () {


        if (isAlreadyClickInFormFields == 0) {
            //jQuery("#payment-buttons-container button").attr("onclick","");
            getNumOfInstallments();
        }



    });

    jQuery('#payment-buttons-container button').attr('onclick','if(!installmentPlanInit(true)){return false;}'+jQuery('#payment-buttons-container button').attr('onclick'));

    jQuery(document).on('change', '#pis_cc_installments_no', function () {
        /*alert('sdsd');*/
        //jQuery('#one-step-checkout-review-terms-agreement-mc_osc_term').prop('checked', false);
        // jQuery('#pis_cc_terms').prop('checked', false);
    });

    // check if we have splitit payment form checkout url for hosted solution
    var isCheckoutUrl = 0;
    var checkIfFoundUrlElse = 0; // check not found checkout url when click redirect button
    jQuery(document).on("click", ".splitit-redirect", function () {

        if (isCheckoutUrl) {
            jQuery(document).find(".redirect-checkbox").prop('checked', true);
            var splititCheckoutUrl = jQuery(document).find(".splitit-redirect").attr("data-splititUrl");
            window.location.href = splititCheckoutUrl;
        } else {
            jQuery(document).find("#p_method_pis_cc").prop('checked', true);
            checkIfFoundUrlElse = 1;
            getNumOfInstallments();
        }

    });

    /*forter code*/

    var cookies = getTokenCookies();
    /*var requestData = {
     "PlanData":{}
     };*/
    var requestData = {};
    if (cookies != null && cookies.length > 0) {
        /*if (!requestData.PlanData["ExtendedParams"]) {
         requestData.PlanData["ExtendedParams"] = {};
         }*/
        for (var i = 0; i < cookies.length; i++) {
            var tempObj = cookies[i];
            var objKeys = Object.keys(tempObj)
            for (var j in objKeys) {
                var key = objKeys[j];
                /*requestData.PlanData.ExtendedParams[key] = tempObj[key];*/
                requestData[key] = tempObj[key];
            }
        }
    }
    console.log(requestData);



    // get external suppliers(fraud detectors) cookie array
    function getTokenCookies() {
        var externalTokens = {
            'Forter': 'forterToken'
        };
        var cookies = [];
        var keys = Object.keys(externalTokens);
        for (var f of keys) {
            var cookie = getCookie(externalTokens[f]);
            if (cookie != -1) {
                var obj = {};
                obj[externalTokens[f]] = cookie;
                cookies.push(obj);
            }
        }
        return cookies;

    }
    // get cookie by name
    function getCookie(name) {
        var cookieKey = name + "=";
        var baseCookies = decodeURIComponent(document.cookie);
        var cookieArray = baseCookies.split(';');

        for (var i = 0; i < cookieArray.length; i++) {
            var cookie = cookieArray[i];
            while (cookie.charAt(0) == ' ') {
                cookie = cookie.substring(1);
            }
            if (cookie.indexOf(cookieKey) > -1) {
                return cookie.substring(cookieKey.length, cookie.length);
            }
        }
        return -1;
    }

    /*forter code*/

    /*jQuery("#payment_form_pis_cc li").on("click",function(){
     alert("sdfsfd");
     });*/

	function getNumOfInstallments() {
	    var selectedInstallment = jQuery("#pis_cc_installments_no").val();
	    jQuery("body").find("#dt_method_pis_cc .pis-login-loader").show();
	    jQuery("body").find(".terms-condition-loader").hide();
	    jQuery.ajax({
	        url: getBaseUrl() + "payitsimple/payment/apiLogin/",
	        type: 'POST',
	        async: true,
	        dataType: 'json',
	        data: {'ForterToken': requestData},
	        success: function (obj) {

	            if (obj.status == true) {
	                /*var html = '<option value="" class="number-of-installments">--Please Select--</option>';
	                 jQuery.each( obj.data, function( index, value ){
	                 html +=  '<option value="'+index+'">'+value+'</option>';
	                 });
	                 jQuery("#pis_cc_installments_no").html(html);*/
	                /*if(obj.installmentNum == 0){
	                 jQuery("#pis_cc_installments_no option:nth-child(2)").attr("value", "");
	                 }*/
	                if ('checkoutUrl' in obj) {

	                    jQuery(document).find(".splitit-redirect").attr('data-splititUrl', obj.checkoutUrl);
	                    isCheckoutUrl = 1;
	                    if (checkIfFoundUrlElse) {
	                        jQuery(document).find(".redirect-checkbox").prop('checked', true);
	                        var splititCheckoutUrl = jQuery(document).find(".splitit-redirect").attr("data-splititUrl");
	                        window.location.href = obj.checkoutUrl;
	                    }

	                    /*jQuery("#payment-buttons-container button").hide();
	                     jQuery(document).find(".splitit-checkout-url").remove();
	                     jQuery(document).find("#payment-buttons-container .back-link").before("<a class='splitit-checkout-url' href='"+obj.checkoutUrl+"' >continue</a>");
	                     */
	                    //jQuery("#payment-buttons-container .back-ling").after("<a href='"+obj.checkoutUrl+"' >redirect</a>");
	                }
	                numOfInstallmentsResponse = 1;
	                isAlreadyClickInFormFields = 1;
	                isLogedIn = 1;
	            } else {
	                isLogedIn = 0;
	                alert(obj.error);
	                //showAjaxMessage(test.message,"danger");
	            }
	            //samePayment = jQuery("#payment-buttons-container button").attr("onclick");
	            //jQuery("#payment-buttons-container button").attr("onclick","");
	            jQuery("body").find("#dt_method_pis_cc .pis-login-loader").hide();

	        },
	        //async:false
	    });
	}
});

function validateFields(){
    // validation for empty fields in splitit form
    var selectedCc = jQuery("#pis_cc_cc_type").val();
    if (selectedCc == "") {
        jQuery("body").find(".terms-condition-loader").hide();
        alert("Please select credit card type.");
        return false;
    }
    var ccNum = jQuery("#pis_cc_cc_number").val();
    if (ccNum == "") {
        jQuery("body").find(".terms-condition-loader").hide();
        alert("Please input Credit card number.");
        return false;
    }
    var ccExp = jQuery("#pis_cc_expiration").val();
    if (ccExp == "") {
        jQuery("body").find(".terms-condition-loader").hide();
        alert("Please select Month.");
        return false;
    }
    var ccYear = jQuery("#pis_cc_expiration_yr").val();
    if (ccYear == "") {
        jQuery("body").find(".terms-condition-loader").hide();
        alert("Please select Year.");
        return false;
    }
    var ccCVV = jQuery("#pis_cc_cc_cid").val();
    if (ccCVV == "") {
        jQuery("body").find(".terms-condition-loader").hide();
        alert("Please input card verification number.");
        return false;
    }
    console.log('#iaprove.is:checked===');
    console.log(jQuery('#iaprove').is(":checked"));
    if(!jQuery('#iaprove').is(":checked")){
    	alert("Please approve Terms and Conditions.");
        return false;	
    }
    return true;
}

// close splitit popup when user check I agree
function paymentSave() {
    if (jQuery('#i_acknowledge').is(":checked")) {
        jQuery(".approval-popup_ovelay").hide();
        // check term checkbox which is hidden
        jQuery(".terms-conditions div").remove();
        jQuery('#pis_cc_terms').prop('checked', true);
        jQuery('#one-step-checkout-review-terms-agreement-mc_osc_term').prop('checked', true);
        jQuery("#approval-popup").hide();
    } else {
    	alert('Please Approve Terms and Conditions.');
        if (!jQuery('#iaprove').is(':checked')) {
            jQuery(".i_ack_err").show();
        } else {
            jQuery('#i_acknowledge').prop('checked', true);
            jQuery('#pis_cc_terms').prop('checked', true);
            jQuery('#one-step-checkout-review-terms-agreement-mc_osc_term').prop('checked', true);
        }
    }


}

// close Approval popup
function closeApprovalPopup() {
    jQuery("#approval-popup, .approval-popup_ovelay").hide();
}

// on click Approve Terms and Conditions button
function installmentPlanInit(isCheckbox) {
    isCheckbox = isCheckbox || false;
    if (!isCheckbox) {
        jQuery("body").find(".terms-condition-loader").css('display', 'inline-block');// show loader of Approve Terms and Conditions button
    }
    // check if splitit login or not
    if (numOfInstallmentsResponse == 0 /*||  checkNewDropDown == 0*/) {
        getNumOfInstallments();
    }
    var selectedInstallment = jQuery("#pis_cc_installments_no").val();
    if (selectedInstallment == "") {
        jQuery("body").find(".terms-condition-loader").hide();
        alert("Please select Number of Installments");
        return false;
    }
    var selectedText = jQuery("#pis_cc_installments_no option:selected").text();
    var instNotAvail = "Installments are not available.";
    var supportLessThan100 = "Splitit only support amount more than 100";
    if (selectedText === instNotAvail) {
        jQuery("body").find(".terms-condition-loader").hide();
        alert(instNotAvail);
        return false;
    }
    if (selectedText === supportLessThan100) {
        jQuery("body").find(".terms-condition-loader").hide();
        alert(supportLessThan100);
        return false;
    }
    if (isCheckbox) {
        // validation for empty fields in splitit form
        var selectedCc = jQuery("#pis_cc_cc_type").val();
        if (selectedCc == "") {
            jQuery("body").find(".terms-condition-loader").hide();
            alert("Please select credit card type.");
            return false;
        }
        var ccNum = jQuery("#pis_cc_cc_number").val();
        if (ccNum == "") {
            jQuery("body").find(".terms-condition-loader").hide();
            alert("Please input Credit card number.");
            return false;
        }
        var ccExp = jQuery("#pis_cc_expiration").val();
        if (ccExp == "") {
            jQuery("body").find(".terms-condition-loader").hide();
            alert("Please select Month.");
            return false;
        }
        var ccYear = jQuery("#pis_cc_expiration_yr").val();
        if (ccYear == "") {
            jQuery("body").find(".terms-condition-loader").hide();
            alert("Please select Year.");
            return false;
        }
        var ccCVV = jQuery("#pis_cc_cc_cid").val();
        if (ccCVV == "") {
            jQuery("body").find(".terms-condition-loader").hide();
            alert("Please input card verification number.");
            return false;
        }
        if(!jQuery('#iaprove').is(":checked")){
        	alert("Please approve Terms and Conditions.");
            return false;
        }
    }

    // uncheck term checkbox which is hidden
    // jQuery('#pis_cc_terms').prop('checked', false);
    var tnCapproved = jQuery('#iaprove').is(":checked");
    if (isLogedIn) {
        jQuery.ajax({
            url: getBaseUrl() + "payitsimple/payment/installmentplaninit/",
            type: 'POST',
            async: false,
            dataType: 'json',
            data: {"selectedInstallment": selectedInstallment, "tnCapproved":tnCapproved },
            success: function (obj) {
                if (!isCheckbox) {
                    if (obj.status == true) {
                        jQuery("#approval-popup").remove();
                        console.log(obj.data);
                        jQuery('body').append(obj.data);

                    } else {
                        alert(obj.data);

                    }
                    jQuery("body").find(".terms-condition-loader").hide();


                }
            }
        });
    } else {
        jQuery("body").find(".terms-condition-loader").hide();
    }
    return true;
}



