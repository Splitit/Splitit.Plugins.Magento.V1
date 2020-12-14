var numOfInstallmentsResponse = 0;
var isLogedIn = 0;
var isLoging = 0;
var curUrl = window.location.href;
var baseUrl = "";
var getNumOfInstallments;
var continueButtonClickEvent;
function getBaseUrl() {
    baseUrl = jQuery(".redirect-class").attr("data-baseurl");
    if (typeof baseUrl === 'undefined') {
        baseUrl = window.location.origin + '/';
    }
    return baseUrl;
}
jQuery(document).ready(function () {
    continueButtonClickEvent = jQuery('#payment-buttons-container button').attr('onclick');

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

    //baseUrl = jQuery("#payment-img").attr("data-baseurl");
    getBaseUrl();

    jQuery(document).on('click', '#checkout-payment-method-load input[type="radio"]', function (e) {
        jQuery("#payment-buttons-container button").attr("onclick",continueButtonClickEvent);
    });
    var isAlreadyClickInFormFields = 0;

    // check if we have splitit payment form checkout url for hosted solution
    var isCheckoutUrl = 0;
    var checkIfFoundUrlElse = 0; // check not found checkout url when click redirect button
    jQuery(document).on("click", ".splitit-redirect", function () {

        if (isCheckoutUrl) {
            jQuery(document).find(".redirect-checkbox").prop('checked', true);
            var splititCheckoutUrl = jQuery(document).find(".splitit-redirect").attr("data-splititUrl");
            window.location.href = splititCheckoutUrl;
        } else {
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

	getNumOfInstallments = function () {
	    jQuery("body").find(".terms-condition-loader").hide();
	    jQuery.ajax({
	        url: getBaseUrl() + "payitsimple/payment/apiLogin/",
	        type: 'POST',
	        async: true,
	        dataType: 'json',
	        data: {'ForterToken': requestData},
	        success: function (obj) {
	            if (obj.status == true) {
	                if ('checkoutUrl' in obj) {
	                    jQuery(document).find(".splitit-redirect").attr('data-splititUrl', obj.checkoutUrl);
	                    isCheckoutUrl = 1;
	                    if (checkIfFoundUrlElse) {
	                        jQuery(document).find(".redirect-checkbox").prop('checked', true);
	                        var splititCheckoutUrl = jQuery(document).find(".splitit-redirect").attr("data-splititUrl");
	                        window.location.href = obj.checkoutUrl;
	                    }
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



