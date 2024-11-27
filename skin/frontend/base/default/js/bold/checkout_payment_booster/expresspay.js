const ExpressPay = async (config, isProductPageActive) => (async (config, isProductPageActive) => {
    'use strict';

    let errorRendered = false;
    let boldPayments;
    let cartTotals;
    let shippingMethodsHtml = '';
    let shippingMethods = [];
    let selectedShippingMethod = {};
    let isProductInCart = true;

    const requiredConfigFields = [
        'epsApiUrl',
        'epsStaticApiUrl',
        'shopDomain',
        'currency',
        'quoteId',
        'quoteTotals',
        'quoteItems',
        'boldCheckoutData',
        'formKey',
        'regions',
    ];

    const defaultConfig = {
        paymentsContainer: 'express-pay-container',
        isFastlaneEnabled: false,
        epsApiUrl: '',
        epsStaticApiUrl: '',
        shopDomain: '',
        currency: '',
        quoteId: 0,
        quoteTotals: {},
        quoteItems: {},
        quoteIsVirtual: false,
        boldCheckoutData: {},
        formKey: '',
        regions: {},
        allowedCountries: ['US', 'CA'],
        defaultProductQuantity: 1,
        addProductToCartUrl: '/checkout/cart/add',
        saveShippingUrl: '/checkout/onepage/saveShipping',
        saveShippingMethodUrl: '/checkout/onepage/saveShippingMethod',
        saveBillingUrl: '/checkout/onepage/saveBilling',
        savePaymentUrl: '/checkout/onepage/savePayment',
        saveOrderUrl: '/checkout/onepage/saveOrder',
        successUrl: '/checkout/onepage/success',
        createOrderUrl: '/checkoutpaymentbooster/expresspay/createOrder',
        updateOrderUrl: '/checkoutpaymentbooster/expresspay/updateOrder',
        getOrderUrl: '/checkoutpaymentbooster/expresspay/getOrder',
        getCartTotalsUrl: '/checkoutpaymentbooster/index/getCartTotals',
    };

    /**
     * @param {Object} config
     * @returns {String[]}
     */
    const validateConfig = config => {
        const errors = [];
        const missingConfigFields = [];

        requiredConfigFields.forEach(field => {
            if (!config.hasOwnProperty(field) || config[field] === null || config[field].length === 0) {
                missingConfigFields.push(field);
            }
        });

        if (missingConfigFields.length > 0) {
            errors.push(
                `Please provide values for the following configuration fields: ${missingConfigFields.join(', ')}`
            );
        }

        return errors;
    };

    /**
     * @param {String} scriptUrl
     * @param {Object} attributes
     * @returns {Promise<void>}
     */
    const loadScript = async (scriptUrl, attributes = {}) => {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');

            script.src = scriptUrl;
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;

            if (attributes.constructor === Object) {
                Object.keys(attributes).forEach((key) => {
                    script.setAttribute(key, attributes[key]);
                });
            }

            document.head.appendChild(script);
        });
    };

    /**
     * @param {Array|String} errorMessages
     * @returns void
     */
    const renderProductPageErrors = errorMessages => {
        const messageContainer = document.getElementById('messages_product_view');
        const messageItems = [];

        if (messageContainer === null) {
            return;
        }

        if (!Array.isArray(errorMessages)) {
            errorMessages = [errorMessages];
        }

        errorMessages.forEach(errorMessage => {
            messageItems.push(`<li><span>${errorMessage}</span></li>`);
        });

        messageContainer.innerHTML = `
            <ul class="messages">
                <li class="notice-msg">
                    <ul>
                        ${messageItems.join('\n')}
                    </ul>
                </li>
            </ul>
        `;
    };

    /**
     * @param {Object} order
     * @param {Object} address
     * @returns {Object}
     */
    const convertExpressPayAddress = (order, address) => {
        address.first_name = order.first_name;
        address.last_name = order.last_name;
        address.state = address.province;
        address.country_code = address.country;
        address.email = order.email;

        delete address.province;
        delete address.country;

        return address;
    };

    /**
     * @param {*} object
     * @param {FormData} formData
     * @param {String} parentKey
     * @returns {FormData}
     */
    const convertObjectToFormData = (object, formData = null, parentKey = '') => {
        if (formData === null) {
            formData = new FormData();
        }

        if (object === null) {
            return formData;
        }

        if (typeof object === 'object') {
            Object.keys(object)
                .forEach(
                    key => {
                        convertObjectToFormData(
                            object[key],
                            formData,
                            parentKey.length > 0 ? `${parentKey}[${key}]` : key
                        );
                    }
                );

            return formData;
        }

        if (Array.isArray(object)) {
            Object.forEach(
                object,
                (value, key) => {
                    convertObjectToFormData(object[key], formData, parentKey.length > 0 ? `${parentKey}[${key}]` : key);
                }
            );

            return formData;
        }

        formData.append(parentKey, object);

        return formData;
    }

    /**
     * @returns {Object}
     */
    const parseShippingMethodsFromHtml = () => {
        let domParser;
        let shippingMethodsDocument;

        if (shippingMethodsHtml.length === 0) {
            return;
        }

        shippingMethods = [];
        domParser = new DOMParser();
        shippingMethodsDocument = domParser.parseFromString(shippingMethodsHtml, 'text/html');

        shippingMethodsDocument.querySelectorAll('dl > dt')
            .forEach(
                (dt) => {
                    let methodInput;
                    let priceElement;

                    const dd = dt.nextElementSibling;
                    const shippingMethod = {
                        name: dt.innerText,
                        code: null
                    };

                    if (dd === null || dd.tagName !== 'DD') {
                        return;
                    }

                    methodInput = dd.querySelector('input[name=shipping_method]');

                    if (methodInput !== null) {
                        shippingMethod.code = methodInput.value;
                    }

                    priceElement = dd.querySelector('.price');

                    if (methodInput !== null) {
                        shippingMethod.price = parseFloat(priceElement.innerText.replace(/[^\d]/g, '') / 100).toFixed(2);
                    }

                    shippingMethods.push(shippingMethod);
                }
            );
    };

    /**
     * @returns {
     *     {
     *         discounts_total: number
     *         fees_total: number
     *         order_balance: number,
     *         shipping_total: number,
     *         taxes_total: number,
     *     }
     * }
     */
    const getOrderTotalsForApplePayAndGooglePay = () => {
        const totals = cartTotals ?? config.quoteTotals;
        const feesTotal = Object.keys(totals)
            .filter(key => !['subtotal', 'discount', 'shipping', 'tax', 'grand_total'].includes(totals[key].code))
            .reduce((accumulator, currentKey) => accumulator + parseFloat(totals[currentKey].value), 0);

        return {
            discounts_total: parseFloat(Math.abs(totals.discount?.value ?? 0).toString(10)) * 100,
            fees_total: feesTotal * 100,
            order_balance: parseFloat(totals.grand_total?.value ?? 0) * 100,
            shipping_total: config.quoteIsVirtual ? 0
                : parseFloat(selectedShippingMethod.price ?? totals.shipping?.value ?? 0) * 100,
            taxes_total: parseFloat(totals.tax?.value ?? 0) * 100
        };
    };

    /**
     * @param {Object} requirements
     * @returns {Object}
     * @throws Error
     */
    const getRequiredOrderData = requirements => {
        const requiredOrderData = {};

        for (const requirement of requirements) {
            switch (requirement) {
                case 'items':
                    requiredOrderData[requirement] = config.quoteItems
                        .map(
                            quoteItem => ({
                                amount: quoteItem.price * 100,
                                label: quoteItem.name
                            })
                        );

                    break;
                case 'shipping_options':
                    if (config.quoteIsVirtual) {
                        requiredOrderData[requirement] = [];
                    } else {
                        requiredOrderData[requirement] = shippingMethods.map(
                            shippingMethod => ({
                                id: shippingMethod.code,
                                label: shippingMethod.name,
                                amount: shippingMethod.price * 100,
                                is_selected: shippingMethod.code === selectedShippingMethod.code
                            })
                        );
                    }

                    break;
                case 'totals':
                    requiredOrderData[requirement] = {
                        order_total: config.quoteTotals.grand_total?.value ?? 0,
                        ...getOrderTotalsForApplePayAndGooglePay()
                    };

                    break;
                case 'customer':
                case 'billing_address':
                case 'shipping_address':
                default:
                    throw new Error(`Requirement "${requirement}" not implemented`);
            }
        }

        return requiredOrderData;
    };

    /**
     * @param {String} gatewayId
     * @returns {Promise<String>}
     * @throws Error
     */
    const createExpressPayOrder = async gatewayId => {
        let createOrderResponse;
        let createOrderResult;
        let errorMessage;

        try {
            createOrderResponse = await fetch(
                config.createOrderUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(
                        {
                            form_key: config.formKey,
                            quote_id: config.quoteId,
                            gateway_id: gatewayId
                        }
                    )
                }
            );
        } catch (error) {
            console.error('Could not create Express Pay order.', error);

            throw error;
        }

        try {
            createOrderResult = await createOrderResponse.json();
        } catch (syntaxError) {
            createOrderResult = {};
        }

        if (!createOrderResponse.ok) {
            if (createOrderResult.hasOwnProperty('error')) {
                errorMessage = createOrderResult.error;
            } else {
                errorMessage = `${createOrderResponse.status} ${createOrderResponse.statusText}`;
            }

            console.error('Could not create Express Pay order.', createOrderResult);

            throw new Error(errorMessage);
        }

        if (!createOrderResult.hasOwnProperty('order_id')) {
            errorMessage = 'Create Express Pay Order API did not return an order ID.';

            console.error(errorMessage);

            throw new Error(errorMessage);
        }

        return createOrderResult.order_id;
    };

    /**
     * @param {String} gatewayId
     * @param {String} orderId
     * @returns {Promise<void>}
     * @throws Error
     */
    const updateExpressPayOrder = async (gatewayId, orderId) => {
        let updateOrderResponse;
        let updateOrderResult;
        let errorMessage;

        try {
            updateOrderResponse = await fetch(
                config.updateOrderUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(
                        {
                            form_key: config.formKey,
                            quote_id: config.quoteId,
                            gateway_id: gatewayId,
                            order_id: orderId,
                        }
                    )
                }
            );
        } catch (error) {
            console.error('Could not update Express Pay order.', error);

            throw error;
        }

        try {
            updateOrderResult = await updateOrderResponse.json();
        } catch (syntaxError) {
            updateOrderResult = {};
        }

        if (!updateOrderResponse.ok) {
            if (updateOrderResult.hasOwnProperty('error')) {
                errorMessage = updateOrderResult.error;
            } else {
                errorMessage = `${updateOrderResponse.status} ${updateOrderResponse.statusText}`;
            }

            console.error('Could not update Express Pay order.', updateOrderResult);

            throw new Error(errorMessage);
        }
    };

    /**
     * @param {String} orderId
     * @param {String} gatewayId
     * @returns {Promise<Object>}
     * @throws Error
     */
    const getExpressPayOrder = async (orderId, gatewayId) => {
        let getOrderResponse;
        let getOrderResult;
        let errorMessage;

        try {
            getOrderResponse = await fetch(
                config.getOrderUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(
                        {
                            form_key: config.formKey,
                            order_id: orderId,
                            gateway_id: gatewayId
                        }
                    )
                }
            );
        } catch (error) {
            console.error('Could not retrieve Express Pay order.', error);

            throw error;
        }

        try {
            getOrderResult = await getOrderResponse.json();
        } catch (syntaxError) {
            getOrderResult = {};
        }

        if (!getOrderResponse.ok) {
            if (getOrderResult.hasOwnProperty('error')) {
                errorMessage = getOrderResult.error;
            } else {
                errorMessage = `${getOrderResponse.status} ${getOrderResponse.statusText}`;
            }

            console.error('Could not retrieve Express Pay order.', getOrderResult);

            throw new Error(errorMessage);
        }

        return getOrderResult;
    };

    /**
     * @returns {Promise<void>}
     * @throws Error
     */
    const addProductToMagentoCart = async () => {
        let addToCartFormData = new FormData();
        let quantity = 0;
        let quantities = [];
        let errorMessage;

        const productAddToCartForm = document.getElementById('product_addtocart_form');
        const validator = new Validation(productAddToCartForm);
        const quantityInputs = document.querySelectorAll('.product-view .qty-wrapper .qty');

        if (!validator.validate()) {
            errorRendered = true;

            throw new Error('Invalid product to add cart form');
        }

        if (quantityInputs.length === 1) {
            quantity = Number(quantityInputs[0].value);

            if (quantity === 0) {
                quantity = config.defaultProductQuantity;
            }

            quantities.push(quantity);

            addToCartFormData.append('qty', quantity);
        }

        if (quantityInputs.length > 1) {
            quantityInputs.forEach(
                quantityInput => {
                    quantity = Number(quantityInput.value);

                    if (quantity === 0) {
                        return;
                    }

                    quantities.push(quantity);

                    addToCartFormData.append(quantityInput.name, quantity);
                }
            );
        }

        if (quantities.reduce((total, newQuantity) => total + newQuantity, 0) === 0) {
            errorMessage = 'Please specify the quantity of product(s).';
            errorRendered = true;

            renderProductPageErrors(errorMessage);

            throw new Error(errorMessage);
        }

        try {
            await fetch(
                config.addProductToCartUrl,
                {
                    method: 'POST',
                    body: addToCartFormData
                }
            );
        } catch (error) {
            errorMessage = 'Could not add product to cart.';

            renderProductPageErrors(errorMessage);

            console.error(errorMessage, error);

            throw error;
        }

        isProductInCart = true;
    };

    /**
     * @param {String} addressType
     * @param {Object} addressData
     * @returns {Promise<void>}
     * @throws Error
     */
    const updateMagentoAddress = async (addressType, addressData) => {
        let street;
        let magentoAddress;
        let countryRegions = {};
        let regionId;
        let updateAddressResponse;
        let updateAddressResponseData;

        const country = addressData.country_code || addressData.countryCode;
        const region = addressData.state || addressData.administrativeArea;

        street = [
            addressData.address_line_1 || addressData.address_line1 || addressData.address1 || '0 Unprovided St',
            addressData.address_line_2 || addressData.address_line2 || addressData.address2 || null
        ];

        if (addressData.hasOwnProperty('addressLines')) {
            street = addressData.addressLines;
        }

        magentoAddress = {
            form_key: config.formKey,
            [addressType]: {
                address_type: addressType,
                quote_id: config.quoteId,
                email: addressData.email || addressData.emailAddress || null,
                firstname: addressData.first_name || addressData.givenName || 'Unknown',
                lastname: addressData.last_name || addressData.familyName || 'Person',
                street: street,
                city: addressData.city || addressData.locality,
                postcode: addressData.postal_code || addressData.postalCode,
                country_id: country,
                telephone: addressData.phone || addressData.phoneNumber || '5555551234',
            }
        };

        if (config.regions.hasOwnProperty(country)) {
            countryRegions = config.regions[country];
        }

        for (regionId in countryRegions) {
            if (countryRegions[regionId].code !== region) {
                continue;
            }

            magentoAddress[addressType].region = countryRegions[regionId].code;
            magentoAddress[addressType].region_id = regionId;

            break;
        }

        try {
            updateAddressResponse = await fetch(
                addressType === 'shipping' ? config.saveShippingUrl : config.saveBillingUrl,
                {
                    method: 'POST',
                    body: convertObjectToFormData(magentoAddress)
                }
            );
        } catch (error) {
            console.error(`Could not update Magento ${addressType} address for Express Pay order.`, error);

            throw error;
        }

        try {
            updateAddressResponseData = await updateAddressResponse.json();
        } catch (syntaxError) {
            updateAddressResponseData = {};
        }

        if (updateAddressResponseData.hasOwnProperty('error') && updateAddressResponseData.error) {
            console.error(
                `Could not update Magento ${addressType} address for Express Pay order.`,
                updateAddressResponseData.message
            );

            throw new Error(updateAddressResponseData.message);
        }

        if (addressType === 'shipping') {
            shippingMethodsHtml = updateAddressResponseData.update_section.html;
        }
    };

    /**
     * @param {Object|null} shippingOptions
     * @returns {Promise<void>}
     * @throws Error
     */
    const updateMagentoShippingMethod = async shippingOptions => {
        let shippingMethodFormData;
        let updateShippingMethodResponse;
        let updateShippingMethodResult;

        if (shippingMethods.length === 0) {
            return;
        }

        if (
            shippingOptions === null
            || (shippingOptions.hasOwnProperty('id') && shippingOptions.id === 'shipping_option_unselected')
        ) {
            selectedShippingMethod = shippingMethods[0];
        } else {
            selectedShippingMethod = shippingMethods.find(
                shippingMethod => shippingMethod.code === (shippingOptions.id ?? shippingOptions.identifier)
            );
        }

        if (selectedShippingMethod === undefined) {
            selectedShippingMethod = {};

            return;
        }

        shippingMethodFormData = new FormData();

        shippingMethodFormData.append('form_key', config.formKey);
        shippingMethodFormData.append('shipping_method', selectedShippingMethod.code);

        try {
            updateShippingMethodResponse = await fetch(
                config.saveShippingMethodUrl,
                {
                    method: 'POST',
                    body: shippingMethodFormData
                }
            );
        } catch (error) {
            console.error(`Could not update Magento shipping method for Express Pay order.`, error);

            throw error;
        }

        try {
            updateShippingMethodResult = await updateShippingMethodResponse.json();
        } catch (error) {
            updateShippingMethodResult = {};
        }

        if (updateShippingMethodResult.hasOwnProperty('error') && updateShippingMethodResult.error) {
            console.error(
                `Could not update Magento shipping method for Express Pay order.`,
                updateShippingMethodResult.message
            );

            throw new Error(updateShippingMethodResult.message);
        }
    };

    /**
     * @param {String} orderId
     * @param {String} paymentType
     * @returns {Promise<void>}
     * @throws Error
     */
    const placeMagentoOrder = async (orderId, paymentType) => {
        let placeOrderResponse;
        let placeOrderResult;

        const orderData = {
            form_key: config.formKey,
            payment: {
                method: 'bold',
                additional_data: {
                    order_id: orderId
                }
            }
        };

        try {
            placeOrderResponse = await fetch(
                config.saveOrderUrl,
                {
                    method: 'POST',
                    body: convertObjectToFormData(orderData)
                }
            );
        } catch (error) {
            console.error('Could not place Express Pay order in Magento.', error);

            throw error;
        }

        /*if (placeOrderResponse.redirected) {
            window.location.href = encodeURI(placeOrderResponse.url);

            return;
        }*/

        try {
            placeOrderResult = await placeOrderResponse.json();
        } catch (error) {
            placeOrderResult = {};
        }

        if (placeOrderResult.hasOwnProperty('error_messages')) {
            let errorMessage;

            console.error(
                'Could not process response from placing Express Pay order in Magento.',
                placeOrderResult.error_messages
            );

            if (Array.isArray(placeOrderResult.error_messages)) {
                errorMessage = placeOrderResult.error_messages.join('\n')
            } else {
                errorMessage = placeOrderResult.error_messages;
            }

            if (paymentType !== 'google') { // Google Pay renders thrown errors inside its pop-up
                alert(errorMessage.stripTags().toString());
            }

            throw new Error(errorMessage);
        }

        window.location.href = encodeURI(config.successUrl);
    };

    /**
     * @returns {Promise<void>}
     */
    const getCartTotals = async () => {
        let getCartTotalsResponse;

        const formData = new FormData();

        formData.append('form_key', config.formKey);

        try {
            getCartTotalsResponse = await fetch(
                config.getCartTotalsUrl,
                {
                    method: 'POST',
                    body: formData
                }
            );
        } catch (error) {
            console.error('Could not retrieve cart totals from Magento.', error);

            throw error;
        }

        try {
            cartTotals = await getCartTotalsResponse.json();
        } catch (syntaxError) {
            cartTotals = null;
        }
    };

    /**
     * @param {Object} paymentData
     * @returns void
     */
    const fixAddressEmailAddresses = paymentData => {
        if (
            !paymentData.billing_address.hasOwnProperty('emailAddress')
            && paymentData.shipping_address.hasOwnProperty('emailAddress')
        ) {
            paymentData.billing_address.emailAddress = paymentData.shipping_address.emailAddress;
        }

        if (
            !paymentData.billing_address.hasOwnProperty('emailAddress')
            && paymentData.hasOwnProperty('customer')
            && paymentData.customer.hasOwnProperty('email_address')
        ) {
            paymentData.billing_address.emailAddress = paymentData.customer.email_address;
        }

        if (
            !paymentData.shipping_address.hasOwnProperty('emailAddress')
            && paymentData.hasOwnProperty('customer')
            && paymentData.customer.hasOwnProperty('email_address')
        ) {
            paymentData.shipping_address.emailAddress = paymentData.customer.email_address;
        }
    };

    /**
     * @param {Object} paymentData
     * @returns void
     */
    const fixBillingAddressPhoneNumber = paymentData => {
        if (
            !paymentData.billing_address.hasOwnProperty('phoneNumber')
            && paymentData.shipping_address.hasOwnProperty('phoneNumber')
        ) {
            paymentData.billing_address.phoneNumber = paymentData.shipping_address.phoneNumber;
        }
    }

    /**
     * @param {Object} paymentData
     * @returns void
     */
    const fixAddressCustomerNames = paymentData => {
        if (!paymentData.hasOwnProperty('customer')) {
            return;
        }

        if (
            paymentData.customer.hasOwnProperty('first_name')
            && !paymentData.billing_address.hasOwnProperty('first_name')
            && !paymentData.billing_address.hasOwnProperty('givenName')
        ) {
            paymentData.billing_address.first_name = paymentData.customer.first_name;
        }

        if (
            paymentData.customer.hasOwnProperty('last_name')
            && !paymentData.billing_address.hasOwnProperty('last_name')
            && !paymentData.billing_address.hasOwnProperty('familyName')
        ) {
            paymentData.billing_address.last_name = paymentData.customer.last_name;
        }

        if (
            paymentData.customer.hasOwnProperty('first_name')
            && !paymentData.shipping_address.hasOwnProperty('first_name')
            && !paymentData.shipping_address.hasOwnProperty('givenName')
        ) {
            paymentData.shipping_address.first_name = paymentData.customer.first_name;
        }

        if (
            paymentData.customer.hasOwnProperty('last_name')
            && !paymentData.shipping_address.hasOwnProperty('last_name')
            && !paymentData.shipping_address.hasOwnProperty('familyName')
        ) {
            paymentData.shipping_address.last_name = paymentData.customer.last_name;
        }
    }

    /**
     * @returns {Promise<void>}
     */
    const initializePaymentsSdk = async () => {
        let sdkConfiguration;

        const isCheckoutActive = window.location.pathname === '/checkout/onepage/';

        if (
            (isCheckoutActive && !config.isFastlaneEnabled)
            || !window.hasOwnProperty('bold')
            || !window.bold.hasOwnProperty('Payments')
        ) {
            await loadScript(config.epsStaticApiUrl + '/js/payments_sdk.js');
        }

        sdkConfiguration = {
            eps_url: config.epsApiUrl,
            eps_bucket_url: config.epsStaticApiUrl,
            group_label: config.shopDomain,
            trace_id: config.boldCheckoutData.public_order_id,
            payment_gateways: [
                {
                    gateway_id: Number(config.boldCheckoutData.flow_settings.eps_gateway_id),
                    auth_token: config.boldCheckoutData.flow_settings.eps_auth_token,
                    currency: config.currency,
                }
            ],
            callbacks: {
                /**
                 * @param {Object} requirements
                 * @returns {Object}
                 * @throws Error
                 */
                onRequireOrderData: requirements => {
                    if (isProductPageActive && !isProductInCart) {
                        throw new Error('No product(s) in cart');
                    }

                    return getRequiredOrderData(requirements);
                },
                /**
                 * @param {String} paymentType
                 * @returns {Promise<void>}
                 * @throws Error
                 */
                onClickPaymentOrder: async (paymentType) => {
                    if (isProductPageActive) {
                        isProductInCart = false;

                        await addProductToMagentoCart(paymentType);
                    }
                },
                /**
                 * @param {String} paymentType
                 * @param {Object} paymentPayload
                 * @returns {Promise<Object>}
                 * @throws Error
                 */
                onCreatePaymentOrder: async (paymentType, paymentPayload) => {
                    let expressPayOrderId;
                    let modifiedPaymentPayload;

                    if (isProductPageActive && !isProductInCart) {
                        throw new Error('No product(s) in cart');
                    }

                    /* We need to work with a copy of the payment payload object to prevent Google Pay from throwing
                       "invalid value" errors because we add data to it. */
                    modifiedPaymentPayload = structuredClone(paymentPayload);

                    if (['apple', 'google'].includes(modifiedPaymentPayload.payment_data.payment_type)) {
                        fixAddressEmailAddresses(modifiedPaymentPayload.payment_data);
                        fixBillingAddressPhoneNumber(modifiedPaymentPayload.payment_data);
                        fixAddressCustomerNames(modifiedPaymentPayload.payment_data);

                        if (!config.quoteIsVirtual) {
                            await updateMagentoAddress('shipping', modifiedPaymentPayload.payment_data.shipping_address);
                        }

                        await updateMagentoAddress('billing', modifiedPaymentPayload.payment_data.billing_address);
                    }

                    expressPayOrderId = await createExpressPayOrder(String(modifiedPaymentPayload.gateway_id));

                    return {
                        payment_data: {
                            id: expressPayOrderId
                        }
                    };
                },
                /**
                 * @param {String} paymentType
                 * @param {Object} paymentPayload
                 * @returns {Promise<Object|void>}
                 */
                onUpdatePaymentOrder: async (paymentType, paymentPayload) => {
                    if (isProductPageActive && !isProductInCart) {
                        throw new Error('No product(s) in cart');
                    }

                    if (!config.quoteIsVirtual) {
                        if (paymentPayload.payment_data.hasOwnProperty('shipping_address')) {
                            await updateMagentoAddress('shipping', paymentPayload.payment_data.shipping_address);

                            parseShippingMethodsFromHtml();
                        }

                        if (paymentPayload.payment_data.hasOwnProperty('shipping_options')) {
                            await updateMagentoShippingMethod(paymentPayload.payment_data.shipping_options);
                        } else {
                            await updateMagentoShippingMethod(null);
                        }
                    }

                    if (!['apple', 'google'].includes(paymentPayload.payment_data.payment_type)) {
                        await updateExpressPayOrder(paymentPayload.gateway_id, paymentPayload.payment_data.order_id);
                    } else {
                        await getCartTotals();

                        return getRequiredOrderData(paymentPayload.require_order_data);
                    }
                },
                /**
                 * @param {String} paymentType
                 * @param {Object} paymentInformation
                 * @param {Object} paymentPayload
                 * @returns {Promise<void>}
                 * @throws Error
                 */
                onApprovePaymentOrder: async (paymentType, paymentInformation, paymentPayload) => {
                    let expressPayOrder;

                    if (!['apple', 'google'].includes(paymentPayload.payment_data.payment_type)) {
                        expressPayOrder = await getExpressPayOrder(
                            paymentPayload.payment_data.order_id,
                            paymentPayload.gateway_id
                        );

                        if (!config.quoteIsVirtual) {
                            await updateMagentoAddress(
                                'shipping',
                                convertExpressPayAddress(expressPayOrder, expressPayOrder.shipping_address)
                            );
                        }

                        await updateMagentoAddress(
                            'billing',
                            convertExpressPayAddress(expressPayOrder, expressPayOrder.billing_address)
                        );
                    }

                    await placeMagentoOrder(
                        paymentPayload.payment_data.order_id,
                        paymentPayload.payment_data.payment_type
                    );
                },
                onErrorPaymentOrder: errors => {
                    if (errorRendered) {
                        errorRendered = false;

                        return;
                    }

                    console.error('An unexpected error occurred while processing the Express Pay order.', errors);

                    alert('An unexpected error occurred. Please try again.');
                }
            }
        };

        if (isCheckoutActive && config.isFastlaneEnabled) {
            while (!window.hasOwnProperty('bold') || !window.bold.hasOwnProperty('Payments')) {
                await new Promise(resolve => setTimeout(resolve, 500));
            }
        }

        boldPayments = new window.bold.Payments(sdkConfiguration);
    };

    /**
     * @returns {Promise<void>}
     */
    const initialize = async () => {
        const validationErrors = validateConfig(config);
        const expressPayContainer = document.getElementById(
            config.paymentsContainer ?? defaultConfig.paymentsContainer
        );

        if (!isProductPageActive && validationErrors.length > 0) {
            if (expressPayContainer !== null) {
                expressPayContainer.style.display = 'none';
            }

            console.error('Could not initialize Express Pay.', validationErrors);

            return;
        }

        config = {...defaultConfig, ...config};

        await initializePaymentsSdk();
    };

    await initialize();

    return {
        /**
         * @returns {Promise<void>}
         */
        render: async () => {
            await boldPayments.renderWalletPayments(
                config.paymentsContainer,
                {
                    allowedCountries: config.allowedCountries,
                    fastlane: config.isFastlaneEnabled,
                    isPhoneRequired: true,
                    shopName: config.shopDomain,
                }
            );
        }
    };
})(config, isProductPageActive);
