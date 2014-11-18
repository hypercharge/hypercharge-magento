function updateInput(fromInput, toInput) {
	var fromElem = document.getElementById(fromInput);
	var toElem = document.getElementById(toInput);
	toElem.value = fromElem.value;
}

Hyper = Class.create({
    initialize: function () {
        // This variable is used for storing fields values on change into JS object
        this.storedFields = $H({});
        // Binded element change observer method
        this.onChange = this.handleChange.bind(this);
    },
    // This method is invoked each time when ajax call
    // for payment method retrieval
    bindFields: function (container) {

        // Saving container for later usage
        this.container = $(container);
        // Initialising every form field by using initField callback.
        var arr = this.container.select('input');
        arr.each(this.initField.bind(this));
        var arr2 = this.container.select('select');
        arr2.each(this.initField.bind(this));
        var arr3 = this.container.select('textarea');
        arr3.each(this.initField.bind(this));
    },
    // This method initialises field in the loop above
    initField: function (field) {
        // Parses ccs class names to retrieve mapped field name
        var fieldName = this.getMappedName(field);

        if (!fieldName) {
            // Ignore non mapped fields
            return;
        }

        field.observe('change', this.onChange);
        // If there is any value already set in the object, set it back to field.
        // It is useful in case if customer returns back to shipping method
        // and afterwards again enters his payment details.
        if (this.storedFields.get(fieldName)) {
            field.value = this.storedFields.get(fieldName);
        }

        //sepa mandate_id and mandate_signature_date are predefined and need to be set as stored
        if (fieldName == 'sepa_mandate_id' || fieldName == 'sepa_mandate_signature_date') {
            this.storedFields.set(
                fieldName,
                field.value
            );
        }
    },
    // This method stores data on element value change
    handleChange: function (evt) {
        var element = Event.element(evt);
        this.storedFields.set(
            this.getMappedName(element),
            element.value
        );
    },
    // Return field name from class name
    getMappedName: function (element) {

        if (element.className.match(/field-(.+)/)) {
            var classNames = element.classNames();
            var fieldName =  classNames.detect(function(item) {
                if (item.substr(0, 6) == 'field-') {
                    return true;
                }
            });
            if (fieldName) {
                return fieldName.substr(6);
            }
        }
        return null;
    },
    // These methods are invoked for settings
    setSubmitUrl: function(url) {
        this.submitUrl = url;
    },
    // Set Shipping Address
    setShippingAddress: function(shippingAddress) {
        this.shippingAddress = shippingAddress;
    },
    // Set Shipping Address
    setCompanyName: function(companyName) {
        this.companyName = companyName;
    },
    // Set DOB
    setDob: function(dob) {
        this.DOB = dob;
    },
    setPaymentMethod: function(method) {
        this.paymentMethod = method;
    },
    setHeaderOrigin: function(origin) {
        this.headerOrigin = origin;
    },
    setSuccessUrl: function(url) {
        this.successUrl = url;
    },
    setErrorUrl: function(url) {
        this.errorUrl = url;
    },
    // This method checks if redirect url
    // is matching submit url of the payment method.
    // In case of url is the same, then it creates form with hidden fields
    // and automatically submits it
    checkForm: function (placeUrl) {         
        var checkUrl = this.submitUrl;
        var n = placeUrl.indexOf('#');
        if (n > 0) {            
            var formUrl = placeUrl.substring(0,n);
            var checkStr = placeUrl.substring(n+1);            
            if (checkStr.length > 0 && checkUrl.length > 0 && checkUrl == checkStr) {                
                //this.createForm(formUrl);
                //this.submitForm();
                this.sendForm(formUrl);
                return true;
            }
        }
        return false;
    },
    // submits form data to payment provider
    sendForm: function (formUrl) {
        var companyName = this.companyName;

        var fields = this.storedFields.keys();
        var json = '{ "payment": { ';
        for (var i= 0, l=fields.length; i < l; i ++) {
            json += "\"" + fields[i] + "\" : \"" + this.storedFields.get(fields[i]) + "\",";
	        // set risk parameters
            if (fields[i] == 'birthday'){
                json += "\"risk_params\":{\""+fields[i]+"\":\"" + this.storedFields.get(fields[i]) + "\"},";
       	    }
        }
        var shippingAddress = this.shippingAddress;

        if (companyName) {
            json += "\"company_name\" : \"" + companyName + "\"," ;
        }

        if (shippingAddress) {
            json += this.shippingAddress;
            // get dob
            var dob = document.getElementById('poa-dob').value;
            if (dob) {
                json += "\"risk_params\":{\"birthday\":\"" + dob + "\"},";
            }
        }

        if (this.DOB) {
            var dobGtd = document.getElementById('gtd-dob').value;
            if (dobGtd) {
                json += "\"risk_params\":{\"birthday\":\"" + dobGtd + "\"},";
            }
        }

        //json += "\"header_origin\" : \"" + this.headerOrigin + "\"," ;
        json += "\"payment_method\" : \"" + this.paymentMethod + "\" } } ";

        var data = json.evalJSON(true);
        var successUrl = this.successUrl;
        var errorUrl = this.errorUrl;


        jQuery.support.cors = true;

        var container = jQuery('#review-buttons-container');
        jQuery.ajax({
            url: formUrl,
            type: 'POST',
            crossDomain: true,
            data: data,
            dataType: "xml",
            headers: { 'origin': this.headerOrigin },
			contentType: "application/text; charset=utf-8",
            success: function(result) {
                var xml = jQuery(result);
                var transactionStatus = xml.find("status").text();

                if (transactionStatus == 'approved' || transactionStatus == 'pending_async') {
                    window.location.href = successUrl;
                    return;
                } else {
                    window.location.href = errorUrl;
                    return;
                }
            },
            error: function(jqXHR, tranStatus, errorThrown) {
                    if (jqXHR.status == 200) {
                        window.location.href = successUrl;
                        return;
                    } else {
                        window.location.href = errorUrl;
                        return;
                    }
            }
        });
    },            
    // Creates form and appending it body element
    createForm: function (formUrl) {        
        if (this.form) {
            return;
        }
        this.form = new Element('form', {
            style:'display:none',
            action: formUrl,
            method: "POST",
            enctype:"multipart/form-data"
        });
        $(document.body).insert(this.form);
    },
    // Submits form with the most recent data
    // from storedFields property
    submitForm: function () {
        if (!this.form) {
            return;
        }
        // Before creation of new elements,
        // we need to remove old ones
        this.form.childElements().invoke('remove');
        var fields = this.storedFields.keys();
        for (var i= 0, l=fields.length; i < l; i ++) {
            // Create new form elements with help of Element class.
            this.form.insert(
                {bottom: new Element(
                    'input',
                    {type: 'hidden', name: fields[i], value: this.storedFields.get(fields[i])}
                )}
            );
        }
        // At this point you send customer to PSP url,
        // that should return him back to your website afterwards.
        this.form.submit();
    },
    updateInput: function (fromInput, toInput) {
        var fromElem = document.getElementById(fromInput);
        var toElem = document.getElementById(toInput);
        toElem.value = fromElem.value;
    }
});
// Our class singleton
Hyper.getInstance = function () {
    if (!this.instance) {
        this.instance = new this();
    }
    return this.instance;
};
Hyper.ReviewRegister = Class.create({
    initialize: function () {        
        // Registers wrapper on DOM tree load event.
        document.observe('dom:loaded', this.register.bind(this));
        // Registers wrapper on AJAX calls, since review object can be overridden in it
        Ajax.Responders.register(this);
    },
    register: function () {
        if (!window.review || review.overriddenOnSave) {
            // In case if review object is not yet available
            // or wrapper was already applied
            return this;
        }
        review.overriddenOnSave = function (transport) {
            try{
                var result = transport.responseText.evalJSON();                
                // Check of redirect url
                if (result.redirect && Hyper.getInstance().checkForm(result.redirect)) {                    
                    return;
                }
            }
            catch (e) { /* some error processing logic */ }
            // Invokation original order save method
            this.nextStep(transport);
        }
        // Replace original onSave with overridden one
        review.onSave = review.overriddenOnSave.bind(review);
    },
    // This one is invoked when AJAX request gets completed
    onComplete: function () {
        this.register.defer();
    }
});
// Invoke ReviewRegister class routines
new Hyper.ReviewRegister();

Validation.add('validate-poa-age','For this payment method you must be at least 18 years old.',function(v) {
    if (v != '') {
        var dateParts = v.split("-");            
        var dob = new Date(dateParts[0], dateParts[1]-1, dateParts[2]);
        var now = new Date();
        age = now - dob;            
        // get 18 years ago date
        var compareDate = new Date(now.getFullYear()-18, now.getMonth(), now.getDate());
        age18 = now - compareDate;                        
        if (age > age18) {
            return true;
        }
        return false;
    }
    return false;
});
Validation.add('validate-gtd-age','For this payment method you must be at least 18 years old.',function(v) {
    if (v != '') {
        var dateParts = v.split("-");
        var dob = new Date(dateParts[0], dateParts[1]-1, dateParts[2]);
        var now = new Date();
        age = now - dob;
        // get 18 years ago date
        var compareDate = new Date(now.getFullYear()-18, now.getMonth(), now.getDate());
        age18 = now - compareDate;
        if (age > age18) {
            return true;
        }
        return false;
    }
    return false;
});
Validation.add('validate-stdpoa-age','For this payment method you must be at least 18 years old.',function(v) {
    if (v != '') {
        var dateParts = v.split("-");
        var dob = new Date(dateParts[0], dateParts[1]-1, dateParts[2]);
        var now = new Date();
        age = now - dob;
        // get 18 years ago date
        var compareDate = new Date(now.getFullYear()-18, now.getMonth(), now.getDate());
        age18 = now - compareDate;
        if (age > age18) {
            return true;
        }
        return false;
    }
    return false;
});
Validation.add('required-agreement','You must agree to the terms and conditions of this payment method.',function(v) {
    return !Validation.get('IsEmpty').test(v);
});
