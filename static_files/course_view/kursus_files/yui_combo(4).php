/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
YUI.add('attribute-base', function(Y) {

    /**
     * The State class maintains state for a collection of named items, with 
     * a varying number of properties defined.
     *
     * It avoids the need to create a separate class for the item, and separate instances 
     * of these classes for each item, by storing the state in a 2 level hash table, 
     * improving performance when the number of items is likely to be large.
     *
     * @constructor
     * @class State
     */
    Y.State = function() { 
        /**
         * Hash of attributes
         * @property data
         */
        this.data = {};
    };

    Y.State.prototype = {

        /**
         * Adds a property to an item.
         *
         * @method add
         * @param name {String} The name of the item.
         * @param key {String} The name of the property.
         * @param val {Any} The value of the property.
         */
        add : function(name, key, val) {
            var d = this.data;
            d[key] = d[key] || {};
            d[key][name] = val;
        },

        /**
         * Adds multiple properties to an item.
         *
         * @method addAll
         * @param name {String} The name of the item.
         * @param o {Object} A hash of property/value pairs.
         */
        addAll: function(name, o) {
            var key;
            for (key in o) {
                if (o.hasOwnProperty(key)) {
                    this.add(name, key, o[key]);
                }
            }
        },

        /**
         * Removes a property from an item.
         *
         * @method remove
         * @param name {String} The name of the item.
         * @param key {String} The property to remove.
         */
        remove: function(name, key) {
            var d = this.data;
            if (d[key] && (name in d[key])) {
                delete d[key][name];
            }
        },

        /**
         * Removes multiple properties from an item, or remove the item completely.
         *
         * @method removeAll
         * @param name {String} The name of the item.
         * @param o {Object|Array} Collection of properties to delete. If not provided, the entire item is removed.
         */
        removeAll: function(name, o) {
            var d = this.data;

            Y.each(o || d, function(v, k) {
                if(Y.Lang.isString(k)) {
                    this.remove(name, k);
                } else {
                    this.remove(name, v);
                }
            }, this);
        },

        /**
         * For a given item, returns the value of the property requested, or undefined if not found.
         *
         * @method get
         * @param name {String} The name of the item
         * @param key {String} Optional. The property value to retrieve.
         * @return {Any} The value of the supplied property.
         */
        get: function(name, key) {
            var d = this.data;
            return (d[key] && name in d[key]) ?  d[key][name] : undefined;
        },

        /**
         * For the given item, returns a disposable object with all of the
         * item's property/value pairs.
         *
         * @method getAll
         * @param name {String} The name of the item
         * @return {Object} An object with property/value pairs for the item.
         */
        getAll : function(name) {
            var d = this.data, o;

            Y.each(d, function(v, k) {
                if (name in d[k]) {
                    o = o || {};
                    o[k] = v[name];
                }
            }, this);

            return o;
        }
    };
    /**
     * The attribute module provides an augmentable Attribute implementation, which 
     * adds configurable attributes and attribute change events to the class being 
     * augmented. It also provides a State class, which is used internally by Attribute,
     * but can also be used independently to provide a name/property/value data structure to
     * store state.
     *
     * @module attribute
     */

    /**
     * The attribute-base submodule provides core attribute handling support, with everything
     * aside from complex attribute handling in the provider's constructor.
     *
     * @module attribute
     * @submodule attribute-base
     */
    var O = Y.Object,
        Lang = Y.Lang,
        EventTarget = Y.EventTarget,

        DOT = ".",
        CHANGE = "Change",

        // Externally configurable props
        GETTER = "getter",
        SETTER = "setter",
        READ_ONLY = "readOnly",
        WRITE_ONCE = "writeOnce",
        INIT_ONLY = "initOnly",
        VALIDATOR = "validator",
        VALUE = "value",
        VALUE_FN = "valueFn",
        BROADCAST = "broadcast",
        LAZY_ADD = "lazyAdd",
        BYPASS_PROXY = "_bypassProxy",

        // Used for internal state management
        ADDED = "added",
        INITIALIZING = "initializing",
        INIT_VALUE = "initValue",
        PUBLISHED = "published",
        DEF_VALUE = "defaultValue",
        LAZY = "lazy",
        IS_LAZY_ADD = "isLazyAdd",

        INVALID_VALUE,

        MODIFIABLE = {};

        // Properties which can be changed after the attribute has been added.
        MODIFIABLE[READ_ONLY] = 1;
        MODIFIABLE[WRITE_ONCE] = 1;
        MODIFIABLE[GETTER] = 1;
        MODIFIABLE[BROADCAST] = 1;

    /**
     * <p>
     * Attribute provides configurable attribute support along with attribute change events. It is designed to be 
     * augmented on to a host class, and provides the host with the ability to configure attributes to store and retrieve state, 
     * along with attribute change events.
     * </p>
     * <p>For example, attributes added to the host can be configured:</p>
     * <ul>
     *     <li>As read only.</li>
     *     <li>As write once.</li>
     *     <li>With a setter function, which can be used to manipulate
     *     values passed to Attribute's <a href="#method_set">set</a> method, before they are stored.</li>
     *     <li>With a getter function, which can be used to manipulate stored values,
     *     before they are returned by Attribute's <a href="#method_get">get</a> method.</li>
     *     <li>With a validator function, to validate values before they are stored.</li>
     * </ul>
     *
     * <p>See the <a href="#method_addAttr">addAttr</a> method, for the complete set of configuration
     * options available for attributes</p>.
     *
     * <p><strong>NOTE:</strong> Most implementations will be better off extending the <a href="Base.html">Base</a> class, 
     * instead of augmenting Attribute directly. Base augments Attribute and will handle the initial configuration 
     * of attributes for derived classes, accounting for values passed into the constructor.</p>
     *
     * @class Attribute
     * @param attrs {Object} The attributes to add during construction (passed through to <a href="#method_addAttrs">addAttrs</a>). These can also be defined on the constructor being augmented with Attribute by defining the ATTRS property on the constructor.
     * @param values {Object} The initial attribute values to apply (passed through to <a href="#method_addAttrs">addAttrs</a>). These are not merged/cloned. The caller is responsible for isolating user provided values if required.
     * @param lazy {boolean} Whether or not to add attributes lazily (passed through to <a href="#method_addAttrs">addAttrs</a>).
     * @uses EventTarget
     */
    function Attribute(attrs, values, lazy) {

        var host = this; // help compression

        // Perf tweak - avoid creating event literals if not required.
        host._ATTR_E_FACADE = {};

        EventTarget.call(host, {emitFacade:true});

        // _conf maintained for backwards compat
        host._conf = host._state = new Y.State();

        host._stateProxy = host._stateProxy || null;
        host._requireAddAttr = host._requireAddAttr || false;

        this._initAttrs(attrs, values, lazy);
    }

    /**
     * <p>The value to return from an attribute setter in order to prevent the set from going through.</p>
     *
     * <p>You can return this value from your setter if you wish to combine validator and setter 
     * functionality into a single setter function, which either returns the massaged value to be stored or 
     * Attribute.INVALID_VALUE to prevent invalid values from being stored.</p>
     *
     * @property INVALID_VALUE
     * @type Object
     * @static
     * @final
     */
    Attribute.INVALID_VALUE = {};
    INVALID_VALUE = Attribute.INVALID_VALUE;

    /**
     * The list of properties which can be configured for 
     * each attribute (e.g. setter, getter, writeOnce etc.).
     *
     * This property is used internally as a whitelist for faster
     * Y.mix operations.
     *
     * @property _ATTR_CFG
     * @type Array
     * @static
     * @protected
     */
    Attribute._ATTR_CFG = [SETTER, GETTER, VALIDATOR, VALUE, VALUE_FN, WRITE_ONCE, READ_ONLY, LAZY_ADD, BROADCAST, BYPASS_PROXY];

    Attribute.prototype = {
        /**
         * <p>
         * Adds an attribute with the provided configuration to the host object.
         * </p>
         * <p>
         * The config argument object supports the following properties:
         * </p>
         * 
         * <dl>
         *    <dt>value &#60;Any&#62;</dt>
         *    <dd>The initial value to set on the attribute</dd>
         *
         *    <dt>valueFn &#60;Function | String&#62;</dt>
         *    <dd>
         *    <p>A function, which will return the initial value to set on the attribute. This is useful
         *    for cases where the attribute configuration is defined statically, but needs to 
         *    reference the host instance ("this") to obtain an initial value. If both the value and valueFn properties are defined, 
         *    the value returned by the valueFn has precedence over the value property, unless it returns undefined, in which 
         *    case the value property is used.</p>
         *
         *    <p>valueFn can also be set to a string, representing the name of the instance method to be used to retrieve the value.</p>
         *    </dd>
         *
         *    <dt>readOnly &#60;boolean&#62;</dt>
         *    <dd>Whether or not the attribute is read only. Attributes having readOnly set to true
         *        cannot be modified by invoking the set method.</dd>
         *
         *    <dt>writeOnce &#60;boolean&#62; or &#60;string&#62;</dt>
         *    <dd>
         *        Whether or not the attribute is "write once". Attributes having writeOnce set to true, 
         *        can only have their values set once, be it through the default configuration, 
         *        constructor configuration arguments, or by invoking set.
         *        <p>The writeOnce attribute can also be set to the string "initOnly", in which case the attribute can only be set during initialization
         *        (when used with Base, this means it can only be set during construction)</p>
         *    </dd>
         *
         *    <dt>setter &#60;Function | String&#62;</dt>
         *    <dd>
         *    <p>The setter function used to massage or normalize the value passed to the set method for the attribute. 
         *    The value returned by the setter will be the final stored value. Returning
         *    <a href="#property_Attribute.INVALID_VALUE">Attribute.INVALID_VALUE</a>, from the setter will prevent
         *    the value from being stored.
         *    </p>
         *    
         *    <p>setter can also be set to a string, representing the name of the instance method to be used as the setter function.</p>
         *    </dd>
         *      
         *    <dt>getter &#60;Function | String&#62;</dt>
         *    <dd>
         *    <p>
         *    The getter function used to massage or normalize the value returned by the get method for the attribute.
         *    The value returned by the getter function is the value which will be returned to the user when they 
         *    invoke get.
         *    </p>
         *
         *    <p>getter can also be set to a string, representing the name of the instance method to be used as the getter function.</p>
         *    </dd>
         *
         *    <dt>validator &#60;Function | String&#62;</dt>
         *    <dd>
         *    <p>
         *    The validator function invoked prior to setting the stored value. Returning
         *    false from the validator function will prevent the value from being stored.
         *    </p>
         *    
         *    <p>validator can also be set to a string, representing the name of the instance method to be used as the validator function.</p>
         *    </dd>
         *    
         *    <dt>broadcast &#60;int&#62;</dt>
         *    <dd>If and how attribute change events for this attribute should be broadcast. See CustomEvent's <a href="CustomEvent.html#property_broadcast">broadcast</a> property for 
         *    valid values. By default attribute change events are not broadcast.</dd>
         *
         *    <dt>lazyAdd &#60;boolean&#62;</dt>
         *    <dd>Whether or not to delay initialization of the attribute until the first call to get/set it. 
         *    This flag can be used to over-ride lazy initialization on a per attribute basis, when adding multiple attributes through 
         *    the <a href="#method_addAttrs">addAttrs</a> method.</dd>
         *
         * </dl>
         *
         * <p>The setter, getter and validator are invoked with the value and name passed in as the first and second arguments, and with
         * the context ("this") set to the host object.</p>
         *
         * <p>Configuration properties outside of the list mentioned above are considered private properties used internally by attribute, and are not intended for public use.</p>
         * 
         * @method addAttr
         *
         * @param {String} name The name of the attribute.
         * @param {Object} config An object with attribute configuration property/value pairs, specifying the configuration for the attribute.
         *
         * <p>
         * <strong>NOTE:</strong> The configuration object is modified when adding an attribute, so if you need 
         * to protect the original values, you will need to merge the object.
         * </p>
         *
         * @param {boolean} lazy (optional) Whether or not to add this attribute lazily (on the first call to get/set). 
         *
         * @return {Object} A reference to the host object.
         *
         * @chainable
         */
        addAttr: function(name, config, lazy) {


            var host = this, // help compression
                state = host._state,
                value,
                hasValue;

            lazy = (LAZY_ADD in config) ? config[LAZY_ADD] : lazy;

            if (lazy && !host.attrAdded(name)) {
                state.add(name, LAZY, config || {});
                state.add(name, ADDED, true);
            } else {


                if (!host.attrAdded(name) || state.get(name, IS_LAZY_ADD)) {

                    config = config || {};

                    hasValue = (VALUE in config);


                    if(hasValue) {
                        // We'll go through set, don't want to set value in config directly
                        value = config.value;
                        delete config.value;
                    }

                    config.added = true;
                    config.initializing = true;

                    state.addAll(name, config);

                    if (hasValue) {
                        // Go through set, so that raw values get normalized/validated
                        host.set(name, value);
                    }

                    state.remove(name, INITIALIZING);
                }
            }

            return host;
        },

        /**
         * Checks if the given attribute has been added to the host
         *
         * @method attrAdded
         * @param {String} name The name of the attribute to check.
         * @return {boolean} true if an attribute with the given name has been added, false if it hasn't. This method will return true for lazily added attributes.
         */
        attrAdded: function(name) {
            return !!this._state.get(name, ADDED);
        },

        /**
         * Updates the configuration of an attribute which has already been added.
         * <p>
         * The properties which can be modified through this interface are limited
         * to the following subset of attributes, which can be safely modified
         * after a value has already been set on the attribute: readOnly, writeOnce, 
         * broadcast and getter.
         * </p>
         * @method modifyAttr
         * @param {String} name The name of the attribute whose configuration is to be updated.
         * @param {Object} config An object with configuration property/value pairs, specifying the configuration properties to modify.
         */
        modifyAttr: function(name, config) {
            var host = this, // help compression
                prop, state;

            if (host.attrAdded(name)) {

                if (host._isLazyAttr(name)) {
                    host._addLazyAttr(name);
                }

                state = host._state;
                for (prop in config) {
                    if (MODIFIABLE[prop] && config.hasOwnProperty(prop)) {
                        state.add(name, prop, config[prop]);

                        // If we reconfigured broadcast, need to republish
                        if (prop === BROADCAST) {
                            state.remove(name, PUBLISHED);
                        }
                    }
                }
            }

        },

        /**
         * Removes an attribute from the host object
         *
         * @method removeAttr
         * @param {String} name The name of the attribute to be removed.
         */
        removeAttr: function(name) {
            this._state.removeAll(name);
        },

        /**
         * Returns the current value of the attribute. If the attribute
         * has been configured with a 'getter' function, this method will delegate
         * to the 'getter' to obtain the value of the attribute.
         *
         * @method get
         *
         * @param {String} name The name of the attribute. If the value of the attribute is an Object, 
         * dot notation can be used to obtain the value of a property of the object (e.g. <code>get("x.y.z")</code>)
         *
         * @return {Any} The value of the attribute
         */
        get : function(name) {
            return this._getAttr(name);
        },

        /**
         * Checks whether or not the attribute is one which has been
         * added lazily and still requires initialization.
         *
         * @method _isLazyAttr
         * @private
         * @param {String} name The name of the attribute
         * @return {boolean} true if it's a lazily added attribute, false otherwise.
         */
        _isLazyAttr: function(name) {
            return this._state.get(name, LAZY);
        },

        /**
         * Finishes initializing an attribute which has been lazily added.
         *
         * @method _addLazyAttr
         * @private
         * @param {Object} name The name of the attribute
         */
        _addLazyAttr: function(name) {
            var state = this._state,
                lazyCfg = state.get(name, LAZY);

            state.add(name, IS_LAZY_ADD, true);
            state.remove(name, LAZY);
            this.addAttr(name, lazyCfg);
        },

        /**
         * Sets the value of an attribute.
         *
         * @method set
         * @chainable
         *
         * @param {String} name The name of the attribute. If the 
         * current value of the attribute is an Object, dot notation can be used
         * to set the value of a property within the object (e.g. <code>set("x.y.z", 5)</code>).
         *
         * @param {Any} value The value to set the attribute to.
         *
         * @param {Object} opts (Optional) Optional event data to be mixed into
         * the event facade passed to subscribers of the attribute's change event. This 
         * can be used as a flexible way to identify the source of a call to set, allowing 
         * the developer to distinguish between set called internally by the host, vs. 
         * set called externally by the application developer.
         *
         * @return {Object} A reference to the host object.
         */
        set : function(name, val, opts) {
            return this._setAttr(name, val, opts);
        },

        /**
         * Resets the attribute (or all attributes) to its initial value, as long as
         * the attribute is not readOnly, or writeOnce.
         *
         * @method reset
         * @param {String} name Optional. The name of the attribute to reset.  If omitted, all attributes are reset.
         * @return {Object} A reference to the host object.
         * @chainable
         */
        reset : function(name) {
            var host = this,  // help compression
                added;

            if (name) {
                if (host._isLazyAttr(name)) {
                    host._addLazyAttr(name);
                }
                host.set(name, host._state.get(name, INIT_VALUE));
            } else {
                added = host._state.data.added;
                Y.each(added, function(v, n) {
                    host.reset(n);
                }, host);
            }
            return host;
        },

        /**
         * Allows setting of readOnly/writeOnce attributes. See <a href="#method_set">set</a> for argument details.
         *
         * @method _set
         * @protected
         * @chainable
         * 
         * @param {String} name The name of the attribute.
         * @param {Any} val The value to set the attribute to.
         * @param {Object} opts (Optional) Optional event data to be mixed into
         * the event facade passed to subscribers of the attribute's change event.
         * @return {Object} A reference to the host object.
         */
        _set : function(name, val, opts) {
            return this._setAttr(name, val, opts, true);
        },

        /**
         * Provides the common implementation for the public get method,
         * allowing Attribute hosts to over-ride either method.
         *
         * See <a href="#method_get">get</a> for argument details.
         *
         * @method _getAttr
         * @protected
         * @chainable
         *
         * @param {String} name The name of the attribute.
         * @return {Any} The value of the attribute.
         */
        _getAttr : function(name) {
            var host = this, // help compression
                fullName = name,
                state = host._state,
                path,
                getter,
                val,
                cfg;

            if (name.indexOf(DOT) !== -1) {
                path = name.split(DOT);
                name = path.shift();
            }

            // On Demand - Should be rare - handles out of order valueFn references
            if (host._tCfgs && host._tCfgs[name]) {
                cfg = {};
                cfg[name] = host._tCfgs[name];
                delete host._tCfgs[name];
                host._addAttrs(cfg, host._tVals);
            }

            // Lazy Init
            if (host._isLazyAttr(name)) {
                host._addLazyAttr(name);
            }

            val = host._getStateVal(name);
            getter = state.get(name, GETTER);

            if (getter && !getter.call) {
                getter = this[getter];
            }

            val = (getter) ? getter.call(host, val, fullName) : val;
            val = (path) ? O.getValue(val, path) : val;

            return val;
        },

        /**
         * Provides the common implementation for the public set and protected _set methods.
         *
         * See <a href="#method_set">set</a> for argument details.
         *
         * @method _setAttr
         * @protected
         * @chainable
         *
         * @param {String} name The name of the attribute.
         * @param {Any} value The value to set the attribute to.
         * @param {Object} opts (Optional) Optional event data to be mixed into
         * the event facade passed to subscribers of the attribute's change event.
         * @param {boolean} force If true, allows the caller to set values for 
         * readOnly or writeOnce attributes which have already been set.
         *
         * @return {Object} A reference to the host object.
         */
        _setAttr : function(name, val, opts, force) {
            var allowSet = true,
                state = this._state,
                stateProxy = this._stateProxy,
                data = state.data,
                initialSet,
                strPath,
                path,
                currVal,
                writeOnce,
                initializing;

            if (name.indexOf(DOT) !== -1) {
                strPath = name;
                path = name.split(DOT);
                name = path.shift();
            }

            if (this._isLazyAttr(name)) {
                this._addLazyAttr(name);
            }

            initialSet = (!data.value || !(name in data.value));

            if (stateProxy && name in stateProxy && !this._state.get(name, BYPASS_PROXY)) {
                // TODO: Value is always set for proxy. Can we do any better? Maybe take a snapshot as the initial value for the first call to set? 
                initialSet = false;
            }

            if (this._requireAddAttr && !this.attrAdded(name)) {
            } else {

                writeOnce = state.get(name, WRITE_ONCE);
                initializing = state.get(name, INITIALIZING);

                if (!initialSet && !force) {

                    if (writeOnce) {
                        allowSet = false;
                    }

                    if (state.get(name, READ_ONLY)) {
                        allowSet = false;
                    }
                }

                if (!initializing && !force && writeOnce === INIT_ONLY) {
                    allowSet = false;
                }

                if (allowSet) {
                    // Don't need currVal if initialSet (might fail in custom getter if it always expects a non-undefined/non-null value)
                    if (!initialSet) {
                        currVal =  this.get(name);
                    }

                    if (path) {
                       val = O.setValue(Y.clone(currVal), path, val);

                       if (val === undefined) {
                           allowSet = false;
                       }
                    }

                    if (allowSet) {
                        if (initializing) {
                            this._setAttrVal(name, strPath, currVal, val);
                        } else {
                            this._fireAttrChange(name, strPath, currVal, val, opts);
                        }
                    }
                }
            }

            return this;
        },

        /**
         * Utility method to help setup the event payload and fire the attribute change event.
         * 
         * @method _fireAttrChange
         * @private
         * @param {String} attrName The name of the attribute
         * @param {String} subAttrName The full path of the property being changed, 
         * if this is a sub-attribute value being change. Otherwise null.
         * @param {Any} currVal The current value of the attribute
         * @param {Any} newVal The new value of the attribute
         * @param {Object} opts Any additional event data to mix into the attribute change event's event facade.
         */
        _fireAttrChange : function(attrName, subAttrName, currVal, newVal, opts) {
            var host = this,
                eventName = attrName + CHANGE,
                state = host._state,
                facade;

            if (!state.get(attrName, PUBLISHED)) {
                host.publish(eventName, {
                    queuable:false,
                    defaultTargetOnly: true, 
                    defaultFn:host._defAttrChangeFn, 
                    silent:true,
                    broadcast : state.get(attrName, BROADCAST)
                });
                state.add(attrName, PUBLISHED, true);
            }

            facade = (opts) ? Y.merge(opts) : host._ATTR_E_FACADE;

            // Not using the single object signature for fire({type:..., newVal:...}), since 
            // we don't want to override type. Changed to the fire(type, {newVal:...}) signature.

            // facade.type = eventName;
            facade.attrName = attrName;
            facade.subAttrName = subAttrName;
            facade.prevVal = currVal;
            facade.newVal = newVal;

            // host.fire(facade);
            host.fire(eventName, facade);
        },

        /**
         * Default function for attribute change events.
         *
         * @private
         * @method _defAttrChangeFn
         * @param {EventFacade} e The event object for attribute change events.
         */
        _defAttrChangeFn : function(e) {
            if (!this._setAttrVal(e.attrName, e.subAttrName, e.prevVal, e.newVal)) {
                // Prevent "after" listeners from being invoked since nothing changed.
                e.stopImmediatePropagation();
            } else {
                e.newVal = this.get(e.attrName);
            }
        },

        /**
         * Gets the stored value for the attribute, from either the 
         * internal state object, or the state proxy if it exits
         * 
         * @method _getStateVal
         * @private
         * @param {String} name The name of the attribute
         * @return {Any} The stored value of the attribute
         */
        _getStateVal : function(name) {
            var stateProxy = this._stateProxy;
            return stateProxy && (name in stateProxy) && !this._state.get(name, BYPASS_PROXY) ? stateProxy[name] : this._state.get(name, VALUE);
        },

        /**
         * Sets the stored value for the attribute, in either the 
         * internal state object, or the state proxy if it exits
         *
         * @method _setStateVal
         * @private
         * @param {String} name The name of the attribute
         * @param {Any} value The value of the attribute
         */
        _setStateVal : function(name, value) {
            var stateProxy = this._stateProxy;
            if (stateProxy && (name in stateProxy) && !this._state.get(name, BYPASS_PROXY)) {
                stateProxy[name] = value;
            } else {
                this._state.add(name, VALUE, value);
            }
        },

        /**
         * Updates the stored value of the attribute in the privately held State object,
         * if validation and setter passes.
         *
         * @method _setAttrVal
         * @private
         * @param {String} attrName The attribute name.
         * @param {String} subAttrName The sub-attribute name, if setting a sub-attribute property ("x.y.z").
         * @param {Any} prevVal The currently stored value of the attribute.
         * @param {Any} newVal The value which is going to be stored.
         * 
         * @return {booolean} true if the new attribute value was stored, false if not.
         */
        _setAttrVal : function(attrName, subAttrName, prevVal, newVal) {

            var host = this,
                allowSet = true,
                state = host._state,

                validator = state.get(attrName, VALIDATOR),
                setter = state.get(attrName, SETTER),
                initializing = state.get(attrName, INITIALIZING),
                prevValRaw = this._getStateVal(attrName),

                name = subAttrName || attrName,
                retVal,
                valid;

            if (validator) {
                if (!validator.call) { 
                    // Assume string - trying to keep critical path tight, so avoiding Lang check
                    validator = this[validator];
                }
                if (validator) {
                    valid = validator.call(host, newVal, name);

                    if (!valid && initializing) {
                        newVal = state.get(attrName, DEF_VALUE);
                        valid = true; // Assume it's valid, for perf.
                    }
                }
            }

            if (!validator || valid) {
                if (setter) {
                    if (!setter.call) {
                        // Assume string - trying to keep critical path tight, so avoiding Lang check
                        setter = this[setter];
                    }
                    if (setter) {
                        retVal = setter.call(host, newVal, name);

                        if (retVal === INVALID_VALUE) {
                            allowSet = false;
                        } else if (retVal !== undefined){
                            newVal = retVal;
                        }
                    }
                }

                if (allowSet) {
                    if(!subAttrName && (newVal === prevValRaw) && !Lang.isObject(newVal)) {
                        allowSet = false;
                    } else {
                        // Store value
                        if (state.get(attrName, INIT_VALUE) === undefined) {
                            state.add(attrName, INIT_VALUE, newVal);
                        }
                        host._setStateVal(attrName, newVal);
                    }
                }

            } else {
                allowSet = false;
            }

            return allowSet;
        },

        /**
         * Sets multiple attribute values.
         *
         * @method setAttrs
         * @param {Object} attrs  An object with attributes name/value pairs.
         * @return {Object} A reference to the host object.
         * @chainable
         */
        setAttrs : function(attrs, opts) {
            return this._setAttrs(attrs, opts);
        },

        /**
         * Implementation behind the public setAttrs method, to set multiple attribute values.
         *
         * @method _setAttrs
         * @protected
         * @param {Object} attrs  An object with attributes name/value pairs.
         * @return {Object} A reference to the host object.
         * @chainable
         */
        _setAttrs : function(attrs, opts) {
            for (var attr in attrs) {
                if ( attrs.hasOwnProperty(attr) ) {
                    this.set(attr, attrs[attr]);
                }
            }
            return this;
        },

        /**
         * Gets multiple attribute values.
         *
         * @method getAttrs
         * @param {Array | boolean} attrs Optional. An array of attribute names. If omitted, all attribute values are
         * returned. If set to true, all attributes modified from their initial values are returned.
         * @return {Object} An object with attribute name/value pairs.
         */
        getAttrs : function(attrs) {
            return this._getAttrs(attrs);
        },

        /**
         * Implementation behind the public getAttrs method, to get multiple attribute values.
         *
         * @method _getAttrs
         * @protected
         * @param {Array | boolean} attrs Optional. An array of attribute names. If omitted, all attribute values are
         * returned. If set to true, all attributes modified from their initial values are returned.
         * @return {Object} An object with attribute name/value pairs.
         */
        _getAttrs : function(attrs) {
            var host = this,
                o = {}, 
                i, l, attr, val,
                modifiedOnly = (attrs === true);

            attrs = (attrs && !modifiedOnly) ? attrs : O.keys(host._state.data.added);

            for (i = 0, l = attrs.length; i < l; i++) {
                // Go through get, to honor cloning/normalization
                attr = attrs[i];
                val = host.get(attr);

                if (!modifiedOnly || host._getStateVal(attr) != host._state.get(attr, INIT_VALUE)) {
                    o[attr] = host.get(attr); 
                }
            }

            return o;
        },

        /**
         * Configures a group of attributes, and sets initial values.
         *
         * <p>
         * <strong>NOTE:</strong> This method does not isolate the configuration object by merging/cloning. 
         * The caller is responsible for merging/cloning the configuration object if required.
         * </p>
         *
         * @method addAttrs
         * @chainable
         *
         * @param {Object} cfgs An object with attribute name/configuration pairs.
         * @param {Object} values An object with attribute name/value pairs, defining the initial values to apply.
         * Values defined in the cfgs argument will be over-written by values in this argument unless defined as read only.
         * @param {boolean} lazy Whether or not to delay the intialization of these attributes until the first call to get/set.
         * Individual attributes can over-ride this behavior by defining a lazyAdd configuration property in their configuration.
         * See <a href="#method_addAttr">addAttr</a>.
         * 
         * @return {Object} A reference to the host object.
         */
        addAttrs : function(cfgs, values, lazy) {
            var host = this; // help compression
            if (cfgs) {
                host._tCfgs = cfgs;
                host._tVals = host._normAttrVals(values);
                host._addAttrs(cfgs, host._tVals, lazy);
                host._tCfgs = host._tVals = null;
            }

            return host;
        },

        /**
         * Implementation behind the public addAttrs method. 
         * 
         * This method is invoked directly by get if it encounters a scenario 
         * in which an attribute's valueFn attempts to obtain the 
         * value an attribute in the same group of attributes, which has not yet 
         * been added (on demand initialization).
         *
         * @method _addAttrs
         * @private
         * @param {Object} cfgs An object with attribute name/configuration pairs.
         * @param {Object} values An object with attribute name/value pairs, defining the initial values to apply.
         * Values defined in the cfgs argument will be over-written by values in this argument unless defined as read only.
         * @param {boolean} lazy Whether or not to delay the intialization of these attributes until the first call to get/set.
         * Individual attributes can over-ride this behavior by defining a lazyAdd configuration property in their configuration.
         * See <a href="#method_addAttr">addAttr</a>.
         */
        _addAttrs : function(cfgs, values, lazy) {
            var host = this, // help compression
                attr,
                attrCfg,
                value;

            for (attr in cfgs) {
                if (cfgs.hasOwnProperty(attr)) {

                    // Not Merging. Caller is responsible for isolating configs
                    attrCfg = cfgs[attr];
                    attrCfg.defaultValue = attrCfg.value;

                    // Handle simple, complex and user values, accounting for read-only
                    value = host._getAttrInitVal(attr, attrCfg, host._tVals);

                    if (value !== undefined) {
                        attrCfg.value = value;
                    }

                    if (host._tCfgs[attr]) {
                        delete host._tCfgs[attr];
                    }

                    host.addAttr(attr, attrCfg, lazy);
                }
            }
        },

        /**
         * Utility method to protect an attribute configuration
         * hash, by merging the entire object and the individual 
         * attr config objects. 
         *
         * @method _protectAttrs
         * @protected
         * @param {Object} attrs A hash of attribute to configuration object pairs.
         * @return {Object} A protected version of the attrs argument.
         */
        _protectAttrs : function(attrs) {
            if (attrs) {
                attrs = Y.merge(attrs);
                for (var attr in attrs) {
                    if (attrs.hasOwnProperty(attr)) {
                        attrs[attr] = Y.merge(attrs[attr]);
                    }
                }
            }
            return attrs;
        },

        /**
         * Utility method to normalize attribute values. The base implementation 
         * simply merges the hash to protect the original.
         *
         * @method _normAttrVals
         * @param {Object} valueHash An object with attribute name/value pairs
         *
         * @return {Object}
         *
         * @private
         */
        _normAttrVals : function(valueHash) {
            return (valueHash) ? Y.merge(valueHash) : null;
        },

        /**
         * Returns the initial value of the given attribute from
         * either the default configuration provided, or the 
         * over-ridden value if it exists in the set of initValues 
         * provided and the attribute is not read-only.
         *
         * @param {String} attr The name of the attribute
         * @param {Object} cfg The attribute configuration object
         * @param {Object} initValues The object with simple and complex attribute name/value pairs returned from _normAttrVals
         *
         * @return {Any} The initial value of the attribute.
         *
         * @method _getAttrInitVal
         * @private
         */
        _getAttrInitVal : function(attr, cfg, initValues) {
            var val, valFn;
            // init value is provided by the user if it exists, else, provided by the config
            if (!cfg[READ_ONLY] && initValues && initValues.hasOwnProperty(attr)) {
                val = initValues[attr];
            } else {
                val = cfg[VALUE];
                valFn = cfg[VALUE_FN];
 
                if (valFn) {
                    if (!valFn.call) {
                        valFn = this[valFn];
                    }
                    if (valFn) {
                        val = valFn.call(this);
                    }
                }
            }


            return val;
        },

        /**
         * Returns an object with the configuration properties (and value)
         * for the given attrubute. If attrName is not provided, returns the
         * configuration properties for all attributes.
         *
         * @method _getAttrCfg
         * @protected
         * @param {String} name Optional. The attribute name. If not provided, the method will return the configuration for all attributes.
         * @return {Object} The configuration properties for the given attribute, or all attributes.
         */
        _getAttrCfg : function(name) {
            var o,
                data = this._state.data;

            if (data) {
                o = {};

                Y.each(data, function(cfg, cfgProp) {
                    if (name) {
                        if(name in cfg) {
                            o[cfgProp] = cfg[name];
                        }
                    } else {
                        Y.each(cfg, function(attrCfg, attr) {
                           o[attr] = o[attr] || {};
                           o[attr][cfgProp] = attrCfg;
                        });
                    }
                });
            }

            return o;
        },

        /**
         * Utility method to set up initial attributes defined during construction, either through the constructor.ATTRS property, or explicitly passed in.
         * 
         * @method _initAttrs
         * @protected
         * @param attrs {Object} The attributes to add during construction (passed through to <a href="#method_addAttrs">addAttrs</a>). These can also be defined on the constructor being augmented with Attribute by defining the ATTRS property on the constructor.
         * @param values {Object} The initial attribute values to apply (passed through to <a href="#method_addAttrs">addAttrs</a>). These are not merged/cloned. The caller is responsible for isolating user provided values if required.
         * @param lazy {boolean} Whether or not to add attributes lazily (passed through to <a href="#method_addAttrs">addAttrs</a>).
         */
        _initAttrs : function(attrs, values, lazy) {
            // ATTRS support for Node, which is not Base based
            attrs = attrs || this.constructor.ATTRS;
    
            var Base = Y.Base;
            if ( attrs && !(Base && Y.instanceOf(this, Base))) {
                this.addAttrs(this._protectAttrs(attrs), values, lazy);
            }
        }
    };

    // Basic prototype augment - no lazy constructor invocation.
    Y.mix(Attribute, EventTarget, false, null, 1);

    Y.Attribute = Attribute;


}, '3.4.1' ,{requires:['event-custom']});
/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
YUI.add('base-base', function(Y) {

    /**
     * The base module provides the Base class, which objects requiring attribute and custom event support can extend. 
     * The module also provides two ways to reuse code - It augments Base with the Plugin.Host interface which provides 
     * plugin support and also provides the Base.build method which provides a way to build custom classes using extensions.
     *
     * @module base
     */

    /**
     * The base-base submodule provides the Base class without the Plugin support, provided by Plugin.Host, 
     * and without the extension support provided by Base.build.
     *
     * @module base
     * @submodule base-base
     */
    var O = Y.Object,
        L = Y.Lang,
        DOT = ".",
        DESTROY = "destroy",
        INIT = "init",
        INITIALIZED = "initialized",
        DESTROYED = "destroyed",
        INITIALIZER = "initializer",
        BUBBLETARGETS = "bubbleTargets",
        _BUBBLETARGETS = "_bubbleTargets",
        OBJECT_CONSTRUCTOR = Object.prototype.constructor,
        DEEP = "deep",
        SHALLOW = "shallow",
        DESTRUCTOR = "destructor",

        Attribute = Y.Attribute,

        _wlmix = function(r, s, wlhash) {
            var p;
            for (p in s) {
                if(wlhash[p]) { 
                    r[p] = s[p];
                }
            }
            return r;
        };

    /**
     * <p>
     * A base class which objects requiring attributes and custom event support can 
     * extend. Base also handles the chaining of initializer and destructor methods across 
     * the hierarchy as part of object construction and destruction. Additionally, attributes configured 
     * through the static <a href="#property_Base.ATTRS">ATTRS</a> property for each class 
     * in the hierarchy will be initialized by Base.
     * </p>
     *
     * <p>
     * The static <a href="#property_Base.NAME">NAME</a> property of each class extending 
     * from Base will be used as the identifier for the class, and is used by Base to prefix 
     * all events fired by instances of that class.
     * </p>
     *
     * @class Base
     * @constructor
     * @uses Attribute
     * @uses Plugin.Host
     *
     * @param {Object} config Object with configuration property name/value pairs. The object can be 
     * used to provide default values for the objects published attributes.
     *
     * <p>
     * The config object can also contain the following non-attribute properties, providing a convenient 
     * way to configure events listeners and plugins for the instance, as part of the constructor call:
     * </p>
     *
     * <dl>
     *     <dt>on</dt>
     *     <dd>An event name to listener function map, to register event listeners for the "on" moment of the event. A constructor convenience property for the <a href="Base.html#method_on">on</a> method.</dd>
     *     <dt>after</dt>
     *     <dd>An event name to listener function map, to register event listeners for the "after" moment of the event. A constructor convenience property for the <a href="Base.html#method_after">after</a> method.</dd>
     *     <dt>bubbleTargets</dt>
     *     <dd>An object, or array of objects, to register as bubble targets for bubbled events fired by this instance. A constructor convenience property for the <a href="EventTarget.html#method_addTarget">addTarget</a> method.</dd>
     *     <dt>plugins</dt>
     *     <dd>A plugin, or array of plugins to be plugged into the instance (see PluginHost's plug method for signature details). A constructor convenience property for the <a href="Plugin.Host.html#method_plug">plug</a> method.</dd>
     * </dl>
     */
    function Base() {

        // So the object can be used as a hash key (as DD does)
        Y.stamp(this);

        Attribute.call(this);

        // If Plugin.Host has been augmented [ through base-pluginhost ], setup it's
        // initial state, but don't initialize Plugins yet. That's done after initialization.
        var PluginHost = Y.Plugin && Y.Plugin.Host;  
        if (this._initPlugins && PluginHost) {
            PluginHost.call(this);
        }

        if (this._lazyAddAttrs !== false) { this._lazyAddAttrs = true; }

        /**
         * The string used to identify the class of this object.
         *
         * @deprecated Use this.constructor.NAME
         * @property name
         * @type String
         */
        this.name = this.constructor.NAME;
        this._eventPrefix = this.constructor.EVENT_PREFIX || this.constructor.NAME;

        this.init.apply(this, arguments);
    }

    /**
     * The list of properties which can be configured for 
     * each attribute (e.g. setter, getter, writeOnce, readOnly etc.)
     *
     * @property _ATTR_CFG
     * @type Array
     * @static
     * @private
     */
    Base._ATTR_CFG = Attribute._ATTR_CFG.concat("cloneDefaultValue");
    Base._ATTR_CFG_HASH = Y.Array.hash(Base._ATTR_CFG);

    /**
     * <p>
     * The string to be used to identify instances of 
     * this class, for example in prefixing events.
     * </p>
     * <p>
     * Classes extending Base, should define their own
     * static NAME property, which should be camelCase by
     * convention (e.g. MyClass.NAME = "myClass";).
     * </p>
     * @property NAME
     * @type String
     * @static
     */
    Base.NAME = "base";

    /**
     * The default set of attributes which will be available for instances of this class, and 
     * their configuration. In addition to the configuration properties listed by 
     * Attribute's <a href="Attribute.html#method_addAttr">addAttr</a> method, the attribute 
     * can also be configured with a "cloneDefaultValue" property, which defines how the statically
     * defined value field should be protected ("shallow", "deep" and false are supported values). 
     *
     * By default if the value is an object literal or an array it will be "shallow" cloned, to 
     * protect the default value.
     *
     * @property ATTRS
     * @type Object
     * @static
     */
    Base.ATTRS = {
        /**
         * Flag indicating whether or not this object
         * has been through the init lifecycle phase.
         *
         * @attribute initialized
         * @readonly
         * @default false
         * @type boolean
         */
        initialized: {
            readOnly:true,
            value:false
        },

        /**
         * Flag indicating whether or not this object
         * has been through the destroy lifecycle phase.
         *
         * @attribute destroyed
         * @readonly
         * @default false
         * @type boolean
         */
        destroyed: {
            readOnly:true,
            value:false
        }
    };

    Base.prototype = {

        /**
         * Init lifecycle method, invoked during construction.
         * Fires the init event prior to setting up attributes and 
         * invoking initializers for the class hierarchy.
         *
         * @method init
         * @chainable
         * @param {Object} config Object with configuration property name/value pairs
         * @return {Base} A reference to this object
         */
        init: function(config) {

            this._yuievt.config.prefix = this._eventPrefix;

            /**
             * <p>
             * Lifecycle event for the init phase, fired prior to initialization. 
             * Invoking the preventDefault() method on the event object provided 
             * to subscribers will prevent initialization from occuring.
             * </p>
             * <p>
             * Subscribers to the "after" momemt of this event, will be notified
             * after initialization of the object is complete (and therefore
             * cannot prevent initialization).
             * </p>
             *
             * @event init
             * @preventable _defInitFn
             * @param {EventFacade} e Event object, with a cfg property which 
             * refers to the configuration object passed to the constructor.
             */
            this.publish(INIT, {
                queuable:false,
                fireOnce:true,
                defaultTargetOnly:true,
                defaultFn:this._defInitFn
            });

            this._preInitEventCfg(config);

            this.fire(INIT, {cfg: config});

            return this;
        },

        /**
         * Handles the special on, after and target properties which allow the user to
         * easily configure on and after listeners as well as bubble targets during 
         * construction, prior to init.
         *
         * @private
         * @method _preInitEventCfg
         * @param {Object} config The user configuration object
         */
        _preInitEventCfg : function(config) {
            if (config) {
                if (config.on) {
                    this.on(config.on);
                }
                if (config.after) {
                    this.after(config.after);
                }
            }

            var i, l, target,
                userTargets = (config && BUBBLETARGETS in config);

            if (userTargets || _BUBBLETARGETS in this) {
                target = userTargets ? (config && config.bubbleTargets) : this._bubbleTargets;
                if (L.isArray(target)) {
                    for (i = 0, l = target.length; i < l; i++) { 
                        this.addTarget(target[i]);
                    }
                } else if (target) {
                    this.addTarget(target);
                }
            }
        },

        /**
         * <p>
         * Destroy lifecycle method. Fires the destroy
         * event, prior to invoking destructors for the
         * class hierarchy.
         * </p>
         * <p>
         * Subscribers to the destroy
         * event can invoke preventDefault on the event object, to prevent destruction
         * from proceeding.
         * </p>
         * @method destroy
         * @return {Base} A reference to this object
         * @chainable
         */
        destroy: function() {

            /**
             * <p>
             * Lifecycle event for the destroy phase, 
             * fired prior to destruction. Invoking the preventDefault 
             * method on the event object provided to subscribers will 
             * prevent destruction from proceeding.
             * </p>
             * <p>
             * Subscribers to the "after" moment of this event, will be notified
             * after destruction is complete (and as a result cannot prevent
             * destruction).
             * </p>
             * @event destroy
             * @preventable _defDestroyFn
             * @param {EventFacade} e Event object
             */
            this.publish(DESTROY, {
                queuable:false,
                fireOnce:true,
                defaultTargetOnly:true,
                defaultFn: this._defDestroyFn
            });
            this.fire(DESTROY);

            this.detachAll();
            return this;
        },

        /**
         * Default init event handler
         *
         * @method _defInitFn
         * @param {EventFacade} e Event object, with a cfg property which 
         * refers to the configuration object passed to the constructor.
         * @protected
         */
        _defInitFn : function(e) {
            this._initHierarchy(e.cfg);
            if (this._initPlugins) {
                // Need to initPlugins manually, to handle constructor parsing, static Plug parsing
                this._initPlugins(e.cfg);
            }
            this._set(INITIALIZED, true);
        },

        /**
         * Default destroy event handler
         *
         * @method _defDestroyFn
         * @param {EventFacade} e Event object
         * @protected
         */
        _defDestroyFn : function(e) {
            if (this._destroyPlugins) {
                this._destroyPlugins();
            }
            this._destroyHierarchy();
            this._set(DESTROYED, true);
        },

        /**
         * Returns the class hierarchy for this object, with Base being the last class in the array.
         *
         * @method _getClasses
         * @protected
         * @return {Function[]} An array of classes (constructor functions), making up the class hierarchy for this object.
         * This value is cached the first time the method, or _getAttrCfgs, is invoked. Subsequent invocations return the 
         * cached value.
         */
        _getClasses : function() {
            if (!this._classes) {
                this._initHierarchyData();
            }
            return this._classes;
        },

        /**
         * Returns an aggregated set of attribute configurations, by traversing the class hierarchy.
         *
         * @method _getAttrCfgs
         * @protected
         * @return {Object} The hash of attribute configurations, aggregated across classes in the hierarchy
         * This value is cached the first time the method, or _getClasses, is invoked. Subsequent invocations return
         * the cached value.
         */
        _getAttrCfgs : function() {
            if (!this._attrs) {
                this._initHierarchyData();
            }
            return this._attrs;
        },

        /**
         * A helper method used when processing ATTRS across the class hierarchy during 
         * initialization. Returns a disposable object with the attributes defined for 
         * the provided class, extracted from the set of all attributes passed in .
         *
         * @method _filterAttrCfs
         * @private
         *
         * @param {Function} clazz The class for which the desired attributes are required.
         * @param {Object} allCfgs The set of all attribute configurations for this instance. 
         * Attributes will be removed from this set, if they belong to the filtered class, so
         * that by the time all classes are processed, allCfgs will be empty.
         * 
         * @return {Object} The set of attributes belonging to the class passed in, in the form
         * of an object with attribute name/configuration pairs.
         */
        _filterAttrCfgs : function(clazz, allCfgs) {
            var cfgs = null, attr, attrs = clazz.ATTRS;

            if (attrs) {
                for (attr in attrs) {
                    if (allCfgs[attr]) {
                        cfgs = cfgs || {};
                        cfgs[attr] = allCfgs[attr];
                        allCfgs[attr] = null;
                    }
                }
            }

            return cfgs;
        },

        /**
         * A helper method used by _getClasses and _getAttrCfgs, which determines both
         * the array of classes and aggregate set of attribute configurations
         * across the class hierarchy for the instance.
         *
         * @method _initHierarchyData
         * @private
         */
        _initHierarchyData : function() {
            var c = this.constructor,
                classes = [],
                attrs = [];

            while (c) {
                // Add to classes
                classes[classes.length] = c;

                // Add to attributes
                if (c.ATTRS) {
                    attrs[attrs.length] = c.ATTRS;
                }
                c = c.superclass ? c.superclass.constructor : null;
            }

            this._classes = classes;
            this._attrs = this._aggregateAttrs(attrs);
        },

        /**
         * A helper method, used by _initHierarchyData to aggregate 
         * attribute configuration across the instances class hierarchy.
         *
         * The method will protect the attribute configuration value to protect the statically defined 
         * default value in ATTRS if required (if the value is an object literal, array or the 
         * attribute configuration has cloneDefaultValue set to shallow or deep).
         *
         * @method _aggregateAttrs
         * @private
         * @param {Array} allAttrs An array of ATTRS definitions across classes in the hierarchy 
         * (subclass first, Base last)
         * @return {Object} The aggregate set of ATTRS definitions for the instance
         */
        _aggregateAttrs : function(allAttrs) {
            var attr,
                attrs,
                cfg,
                val,
                path,
                i,
                clone, 
                cfgPropsHash = Base._ATTR_CFG_HASH,
                aggAttrs = {};

            if (allAttrs) {
                for (i = allAttrs.length-1; i >= 0; --i) {
                    attrs = allAttrs[i];

                    for (attr in attrs) {
                        if (attrs.hasOwnProperty(attr)) {

                            // Protect config passed in
                            //cfg = Y.mix({}, attrs[attr], true, cfgProps);
                            //cfg = Y.Object(attrs[attr]);
                            cfg = _wlmix({}, attrs[attr], cfgPropsHash);

                            val = cfg.value;
                            clone = cfg.cloneDefaultValue;

                            if (val) {
                                if ( (clone === undefined && (OBJECT_CONSTRUCTOR === val.constructor || L.isArray(val))) || clone === DEEP || clone === true) {
                                    cfg.value = Y.clone(val);
                                } else if (clone === SHALLOW) {
                                    cfg.value = Y.merge(val);
                                }
                                // else if (clone === false), don't clone the static default value. 
                                // It's intended to be used by reference.
                            }

                            path = null;
                            if (attr.indexOf(DOT) !== -1) {
                                path = attr.split(DOT);
                                attr = path.shift();
                            }

                            if (path && aggAttrs[attr] && aggAttrs[attr].value) {
                                O.setValue(aggAttrs[attr].value, path, val);
                            } else if (!path) {
                                if (!aggAttrs[attr]) {
                                    aggAttrs[attr] = cfg;
                                } else {
                                    _wlmix(aggAttrs[attr], cfg, cfgPropsHash);
                                }
                            }
                        }
                    }
                }
            }

            return aggAttrs;
        },

        /**
         * Initializes the class hierarchy for the instance, which includes 
         * initializing attributes for each class defined in the class's 
         * static <a href="#property_Base.ATTRS">ATTRS</a> property and 
         * invoking the initializer method on the prototype of each class in the hierarchy.
         *
         * @method _initHierarchy
         * @param {Object} userVals Object with configuration property name/value pairs
         * @private
         */
        _initHierarchy : function(userVals) {
            var lazy = this._lazyAddAttrs,
                constr,
                constrProto,
                ci,
                ei,
                el,
                extProto,
                exts,
                classes = this._getClasses(),
                attrCfgs = this._getAttrCfgs();

            for (ci = classes.length-1; ci >= 0; ci--) {

                constr = classes[ci];
                constrProto = constr.prototype;
                exts = constr._yuibuild && constr._yuibuild.exts; 

                if (exts) {
                    for (ei = 0, el = exts.length; ei < el; ei++) {
                        exts[ei].apply(this, arguments);
                    }
                }

                this.addAttrs(this._filterAttrCfgs(constr, attrCfgs), userVals, lazy);

                // Using INITIALIZER in hasOwnProperty check, for performance reasons (helps IE6 avoid GC thresholds when
                // referencing string literals). Not using it in apply, again, for performance "." is faster. 
                if (constrProto.hasOwnProperty(INITIALIZER)) {
                    constrProto.initializer.apply(this, arguments);
                }

                if (exts) {
                    for (ei = 0; ei < el; ei++) {
                        extProto = exts[ei].prototype;
                        if (extProto.hasOwnProperty(INITIALIZER)) {
                            extProto.initializer.apply(this, arguments);
                        }
                    }
                }
            }
        },

        /**
         * Destroys the class hierarchy for this instance by invoking
         * the destructor method on the prototype of each class in the hierarchy.
         *
         * @method _destroyHierarchy
         * @private
         */
        _destroyHierarchy : function() {
            var constr,
                constrProto,
                ci, cl, ei, el, exts, extProto,
                classes = this._getClasses();

            for (ci = 0, cl = classes.length; ci < cl; ci++) {
                constr = classes[ci];
                constrProto = constr.prototype;
                exts = constr._yuibuild && constr._yuibuild.exts; 

                if (exts) {
                    for (ei = 0, el = exts.length; ei < el; ei++) {
                        extProto = exts[ei].prototype;
                        if (extProto.hasOwnProperty(DESTRUCTOR)) {
                            extProto.destructor.apply(this, arguments);
                        }
                    }
                }

                if (constrProto.hasOwnProperty(DESTRUCTOR)) {
                    constrProto.destructor.apply(this, arguments);
                }
            }
        },

        /**
         * Default toString implementation. Provides the constructor NAME
         * and the instance guid, if set.
         *
         * @method toString
         * @return {String} String representation for this object
         */
        toString: function() {
            return this.name + "[" + Y.stamp(this, true) + "]";
        }

    };

    // Straightup augment, no wrapper functions
    Y.mix(Base, Attribute, false, null, 1);

    // Fix constructor
    Base.prototype.constructor = Base;

    Y.Base = Base;


}, '3.4.1' ,{requires:['attribute-base']});
/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
YUI.add('base-pluginhost', function(Y) {

    /**
     * The base-pluginhost submodule adds Plugin support to Base, by augmenting Base with 
     * Plugin.Host and setting up static (class level) Base.plug and Base.unplug methods.
     *
     * @module base
     * @submodule base-pluginhost
     * @for Base
     */

    var Base = Y.Base,
        PluginHost = Y.Plugin.Host;

    Y.mix(Base, PluginHost, false, null, 1);

    /**
     * Alias for <a href="Plugin.Host.html#method_Plugin.Host.plug">Plugin.Host.plug</a>. See aliased 
     * method for argument and return value details.
     *
     * @method plug
     * @static
     */
    Base.plug = PluginHost.plug;

    /**
     * Alias for <a href="Plugin.Host.html#method_Plugin.Host.unplug">Plugin.Host.unplug</a>. See the 
     * aliased method for argument and return value details.
     *
     * @method unplug
     * @static
     */
    Base.unplug = PluginHost.unplug;


}, '3.4.1' ,{requires:['base-base', 'pluginhost']});
/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
YUI.add('base-build', function(Y) {

    /**
     * The base-build submodule provides Base.build functionality, which
     * can be used to create custom classes, by aggregating extensions onto 
     * a main class.
     *
     * @module base
     * @submodule base-build
     * @for Base
     */
    var Base = Y.Base,
        L = Y.Lang,
        INITIALIZER = "initializer",
        DESTRUCTOR = "destructor",
        build;

    Base._build = function(name, main, extensions, px, sx, cfg) {

        var build = Base._build,

            builtClass = build._ctor(main, cfg),
            buildCfg = build._cfg(main, cfg),

            _mixCust = build._mixCust,

            aggregates = buildCfg.aggregates,
            custom = buildCfg.custom,

            dynamic = builtClass._yuibuild.dynamic,

            i, l, val, extClass, extProto,
            initializer,
            destructor;

        if (dynamic && aggregates) {
            for (i = 0, l = aggregates.length; i < l; ++i) {
                val = aggregates[i];
                if (main.hasOwnProperty(val)) {
                    builtClass[val] = L.isArray(main[val]) ? [] : {};
                }
            }
        }

        // Augment/Aggregate
        for (i = 0, l = extensions.length; i < l; i++) {
            extClass = extensions[i];

            extProto = extClass.prototype;
            
            initializer = extProto[INITIALIZER];
            destructor = extProto[DESTRUCTOR];
            delete extProto[INITIALIZER];
            delete extProto[DESTRUCTOR];

            // Prototype, old non-displacing augment
            Y.mix(builtClass, extClass, true, null, 1);

             // Custom Statics
            _mixCust(builtClass, extClass, aggregates, custom);
            
            if (initializer) { 
                extProto[INITIALIZER] = initializer;
            }

            if (destructor) {
                extProto[DESTRUCTOR] = destructor;
            }

            builtClass._yuibuild.exts.push(extClass);
        }

        if (px) {
            Y.mix(builtClass.prototype, px, true);
        }

        if (sx) {
            Y.mix(builtClass, build._clean(sx, aggregates, custom), true);
            _mixCust(builtClass, sx, aggregates, custom);
        }

        builtClass.prototype.hasImpl = build._impl;

        if (dynamic) {
            builtClass.NAME = name;
            builtClass.prototype.constructor = builtClass;
        }

        return builtClass;
    };

    build = Base._build;

    Y.mix(build, {

        _mixCust: function(r, s, aggregates, custom) {

            if (aggregates) {
                Y.aggregate(r, s, true, aggregates);
            }

            if (custom) {
                for (var j in custom) {
                    if (custom.hasOwnProperty(j)) {
                        custom[j](j, r, s);
                    }
                }
            }
        },

        _tmpl: function(main) {

            function BuiltClass() {
                BuiltClass.superclass.constructor.apply(this, arguments);
            }
            Y.extend(BuiltClass, main);

            return BuiltClass;
        },

        _impl : function(extClass) {
            var classes = this._getClasses(), i, l, cls, exts, ll, j;
            for (i = 0, l = classes.length; i < l; i++) {
                cls = classes[i];
                if (cls._yuibuild) {
                    exts = cls._yuibuild.exts;
                    ll = exts.length;
    
                    for (j = 0; j < ll; j++) {
                        if (exts[j] === extClass) {
                            return true;
                        }
                    }
                }
            }
            return false;
        },

        _ctor : function(main, cfg) {

           var dynamic = (cfg && false === cfg.dynamic) ? false : true,
               builtClass = (dynamic) ? build._tmpl(main) : main,
               buildCfg = builtClass._yuibuild;

            if (!buildCfg) {
                buildCfg = builtClass._yuibuild = {};
            }

            buildCfg.id = buildCfg.id || null;
            buildCfg.exts = buildCfg.exts || [];
            buildCfg.dynamic = dynamic;

            return builtClass;
        },

        _cfg : function(main, cfg) {
            var aggr = [], 
                cust = {},
                buildCfg,
                cfgAggr = (cfg && cfg.aggregates),
                cfgCustBuild = (cfg && cfg.custom),
                c = main;

            while (c && c.prototype) {
                buildCfg = c._buildCfg; 
                if (buildCfg) {
                    if (buildCfg.aggregates) {
                        aggr = aggr.concat(buildCfg.aggregates);
                    }
                    if (buildCfg.custom) {
                        Y.mix(cust, buildCfg.custom, true);
                    }
                }
                c = c.superclass ? c.superclass.constructor : null;
            }

            if (cfgAggr) {
                aggr = aggr.concat(cfgAggr);
            }
            if (cfgCustBuild) {
                Y.mix(cust, cfg.cfgBuild, true);
            }

            return {
                aggregates: aggr,
                custom: cust
            };
        },

        _clean : function(sx, aggregates, custom) {
            var prop, i, l, sxclone = Y.merge(sx);

            for (prop in custom) {
                if (sxclone.hasOwnProperty(prop)) {
                    delete sxclone[prop];
                }
            }

            for (i = 0, l = aggregates.length; i < l; i++) {
                prop = aggregates[i];
                if (sxclone.hasOwnProperty(prop)) {
                    delete sxclone[prop];
                }
            }

            return sxclone;
        }
    });

    /**
     * <p>
     * Builds a custom constructor function (class) from the
     * main function, and array of extension functions (classes)
     * provided. The NAME field for the constructor function is 
     * defined by the first argument passed in.
     * </p>
     * <p>
     * The cfg object supports the following properties
     * </p>
     * <dl>
     *    <dt>dynamic &#60;boolean&#62;</dt>
     *    <dd>
     *    <p>If true (default), a completely new class
     *    is created which extends the main class, and acts as the 
     *    host on which the extension classes are augmented.</p>
     *    <p>If false, the extensions classes are augmented directly to
     *    the main class, modifying the main class' prototype.</p>
     *    </dd>
     *    <dt>aggregates &#60;String[]&#62;</dt>
     *    <dd>An array of static property names, which will get aggregated
     *    on to the built class, in addition to the default properties build 
     *    will always aggregate as defined by the main class' static _buildCfg
     *    property.
     *    </dd>
     * </dl>
     *
     * @method build
     * @deprecated Use the more convenient Base.create and Base.mix methods instead
     * @static
     * @param {Function} name The name of the new class. Used to defined the NAME property for the new class.
     * @param {Function} main The main class on which to base the built class
     * @param {Function[]} extensions The set of extension classes which will be
     * augmented/aggregated to the built class.
     * @param {Object} cfg Optional. Build configuration for the class (see description).
     * @return {Function} A custom class, created from the provided main and extension classes
     */
    Base.build = function(name, main, extensions, cfg) {
        return build(name, main, extensions, null, null, cfg);
    };

    /**
     * <p>Creates a new class (constructor function) which extends the base class passed in as the second argument, 
     * and mixes in the array of extensions provided.</p>
     * <p>Prototype properties or methods can be added to the new class, using the px argument (similar to Y.extend).</p>
     * <p>Static properties or methods can be added to the new class, using the sx argument (similar to Y.extend).</p>
     * <p>
     * 
     * </p>
     * @method create
     * @static
     * @param {Function} name The name of the newly created class. Used to defined the NAME property for the new class.
     * @param {Function} main The base class which the new class should extend. This class needs to be Base or a class derived from base (e.g. Widget).
     * @param {Function[]} extensions The list of extensions which will be mixed into the built class.
     * @param {Object} px The set of prototype properties/methods to add to the built class.
     * @param {Object} sx The set of static properties/methods to add to the built class.
     * @return {Function} The newly created class.
     */
    Base.create = function(name, base, extensions, px, sx) {
        return build(name, base, extensions, px, sx);
    };

    /**
     * <p>Mixes in a list of extensions to an existing class.</p>
     * @method mix
     * @static
     * @param {Function} main The existing class into which the extensions should be mixed.  The class needs to be Base or a class derived from Base (e.g. Widget)
     * @param {Function[]} extensions The set of extension classes which will mixed into the existing main class.
     * @return {Function} The modified main class, with extensions mixed in.
     */
    Base.mix = function(main, extensions) {
        return build(null, main, extensions, null, null, {dynamic:false});
    };

    /**
     * The build configuration for the Base class.
     *
     * Defines the static fields which need to be aggregated
     * when the Base class is used as the main class passed to
     * the <a href="#method_Base.build">Base.build</a> method.
     *
     * @property _buildCfg
     * @type Object
     * @static
     * @final
     * @private
     */
    Base._buildCfg = {
        custom : {
            ATTRS : function(prop, r, s) {

                r.ATTRS = r.ATTRS || {};

                if (s.ATTRS) {

                    var sAttrs = s.ATTRS,
                        rAttrs = r.ATTRS,
                        a;

                    for (a in sAttrs) {
                        if (sAttrs.hasOwnProperty(a)) {
                            rAttrs[a] = rAttrs[a] || {};
                            Y.mix(rAttrs[a], sAttrs[a], true);
                        }
                    }
                }
            }
        },
        aggregates : ["_PLUG", "_UNPLUG"]
    };


}, '3.4.1' ,{requires:['base-base']});
/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
YUI.add('event-synthetic', function(Y) {

/* Define new DOM events that can be subscribed to from Nodes.
 *
 * @module event
 * @submodule event-synthetic
 */
var DOMMap   = Y.Env.evt.dom_map,
    toArray  = Y.Array,
    YLang    = Y.Lang,
    isObject = YLang.isObject,
    isString = YLang.isString,
    isArray  = YLang.isArray,
    query    = Y.Selector.query,
    noop     = function () {};

/**
 * <p>The triggering mechanism used by SyntheticEvents.</p>
 *
 * <p>Implementers should not instantiate these directly.  Use the Notifier
 * provided to the event's implemented <code>on(node, sub, notifier)</code> or
 * <code>delegate(node, sub, notifier, filter)</code> methods.</p>
 *
 * @class SyntheticEvent.Notifier
 * @constructor
 * @param handle {EventHandle} the detach handle for the subscription to an
 *              internal custom event used to execute the callback passed to
 *              on(..) or delegate(..)
 * @param emitFacade {Boolean} take steps to ensure the first arg received by
 *              the subscription callback is an event facade
 * @private
 * @since 3.2.0
 */
function Notifier(handle, emitFacade) {
    this.handle     = handle;
    this.emitFacade = emitFacade;
}

/**
 * <p>Executes the subscription callback, passing the firing arguments as the
 * first parameters to that callback. For events that are configured with
 * emitFacade=true, it is common practice to pass the triggering DOMEventFacade
 * as the first parameter.  Barring a proper DOMEventFacade or EventFacade
 * (from a CustomEvent), a new EventFacade will be generated.  In that case, if
 * fire() is called with a simple object, it will be mixed into the facade.
 * Otherwise, the facade will be prepended to the callback parameters.</p>
 *
 * <p>For notifiers provided to delegate logic, the first argument should be an
 * object with a &quot;currentTarget&quot; property to identify what object to
 * default as 'this' in the callback.  Typically this is gleaned from the
 * DOMEventFacade or EventFacade, but if configured with emitFacade=false, an
 * object must be provided.  In that case, the object will be removed from the
 * callback parameters.</p>
 *
 * <p>Additional arguments passed during event subscription will be
 * automatically added after those passed to fire().</p>
 *
 * @method fire
 * @param e {EventFacade|DOMEventFacade|Object|any} (see description)
 * @param arg* {any} additional arguments received by all subscriptions
 * @private
 */
Notifier.prototype.fire = function (e) {
    // first arg to delegate notifier should be an object with currentTarget
    var args     = toArray(arguments, 0, true),
        handle   = this.handle,
        ce       = handle.evt,
        sub      = handle.sub,
        thisObj  = sub.context,
        delegate = sub.filter,
        event    = e || {},
        ret;

    if (this.emitFacade) {
        if (!e || !e.preventDefault) {
            event = ce._getFacade();

            if (isObject(e) && !e.preventDefault) {
                Y.mix(event, e, true);
                args[0] = event;
            } else {
                args.unshift(event);
            }
        }

        event.type    = ce.type;
        event.details = args.slice();

        if (delegate) {
            event.container = ce.host;
        }
    } else if (delegate && isObject(e) && e.currentTarget) {
        args.shift();
    }

    sub.context = thisObj || event.currentTarget || ce.host;
    ret = ce.fire.apply(ce, args);
    sub.context = thisObj; // reset for future firing

    // to capture callbacks that return false to stopPropagation.
    // Useful for delegate implementations
    return ret;
};

/**
 * Manager object for synthetic event subscriptions to aggregate multiple synths on the same node without colliding with actual DOM subscription entries in the global map of DOM subscriptions.  Also facilitates proper cleanup on page unload.
 *
 * @class SynthRegistry
 * @constructor
 * @param el {HTMLElement} the DOM element
 * @param yuid {String} the yuid stamp for the element
 * @param key {String} the generated id token used to identify an event type +
 *                     element in the global DOM subscription map.
 * @private
 */
function SynthRegistry(el, yuid, key) {
    this.handles = [];
    this.el      = el;
    this.key     = key;
    this.domkey  = yuid;
}

SynthRegistry.prototype = {
    constructor: SynthRegistry,

    // A few object properties to fake the CustomEvent interface for page
    // unload cleanup.  DON'T TOUCH!
    type      : '_synth',
    fn        : noop,
    capture   : false,

    /**
     * Adds a subscription from the Notifier registry.
     *
     * @method register
     * @param handle {EventHandle} the subscription
     * @since 3.4.0
     */
    register: function (handle) {
        handle.evt.registry = this;
        this.handles.push(handle);
    },

    /**
     * Removes the subscription from the Notifier registry.
     *
     * @method _unregisterSub
     * @param sub {Subscription} the subscription
     * @since 3.4.0
     */
    unregister: function (sub) {
        var handles = this.handles,
            events = DOMMap[this.domkey],
            i;

        for (i = handles.length - 1; i >= 0; --i) {
            if (handles[i].sub === sub) {
                handles.splice(i, 1);
                break;
            }
        }

        // Clean up left over objects when there are no more subscribers.
        if (!handles.length) {
            delete events[this.key];
            if (!Y.Object.size(events)) {
                delete DOMMap[this.domkey];
            }
        }
    },

    /**
     * Used by the event system's unload cleanup process.  When navigating
     * away from the page, the event system iterates the global map of element
     * subscriptions and detaches everything using detachAll().  Normally,
     * the map is populated with custom events, so this object needs to
     * at least support the detachAll method to duck type its way to
     * cleanliness.
     *
     * @method detachAll
     * @private
     * @since 3.4.0
     */
    detachAll : function () {
        var handles = this.handles,
            i = handles.length;

        while (--i >= 0) {
            handles[i].detach();
        }
    }
};

/**
 * <p>Wrapper class for the integration of new events into the YUI event
 * infrastructure.  Don't instantiate this object directly, use
 * <code>Y.Event.define(type, config)</code>.  See that method for details.</p>
 *
 * <p>Properties that MAY or SHOULD be specified in the configuration are noted
 * below and in the description of <code>Y.Event.define</code>.</p>
 *
 * @class SyntheticEvent
 * @constructor
 * @param cfg {Object} Implementation pieces and configuration
 * @since 3.1.0
 * @in event-synthetic
 */
function SyntheticEvent() {
    this._init.apply(this, arguments);
}

Y.mix(SyntheticEvent, {
    Notifier: Notifier,
    SynthRegistry: SynthRegistry,

    /**
     * Returns the array of subscription handles for a node for the given event
     * type.  Passing true as the third argument will create a registry entry
     * in the event system's DOM map to host the array if one doesn't yet exist.
     *
     * @method getRegistry
     * @param node {Node} the node
     * @param type {String} the event
     * @param create {Boolean} create a registration entry to host a new array
     *                  if one doesn't exist.
     * @return {Array}
     * @static
     * @protected
     * @since 3.2.0
     */
    getRegistry: function (node, type, create) {
        var el     = node._node,
            yuid   = Y.stamp(el),
            key    = 'event:' + yuid + type + '_synth',
            events = DOMMap[yuid];
            
        if (create) {
            if (!events) {
                events = DOMMap[yuid] = {};
            }
            if (!events[key]) {
                events[key] = new SynthRegistry(el, yuid, key);
            }
        }

        return (events && events[key]) || null;
    },

    /**
     * Alternate <code>_delete()</code> method for the CustomEvent object
     * created to manage SyntheticEvent subscriptions.
     *
     * @method _deleteSub
     * @param sub {Subscription} the subscription to clean up
     * @private
     * @since 3.2.0
     */
    _deleteSub: function (sub) {
        if (sub && sub.fn) {
            var synth = this.eventDef,
                method = (sub.filter) ? 'detachDelegate' : 'detach';

            this.subscribers = {};
            this.subCount = 0;

            synth[method](sub.node, sub, this.notifier, sub.filter);
            this.registry.unregister(sub);

            delete sub.fn;
            delete sub.node;
            delete sub.context;
        }
    },

    prototype: {
        constructor: SyntheticEvent,

        /**
         * Construction logic for the event.
         *
         * @method _init
         * @protected
         */
        _init: function () {
            var config = this.publishConfig || (this.publishConfig = {});

            // The notification mechanism handles facade creation
            this.emitFacade = ('emitFacade' in config) ?
                                config.emitFacade :
                                true;
            config.emitFacade  = false;
        },

        /**
         * <p>Implementers MAY provide this method definition.</p>
         *
         * <p>Implement this function if the event supports a different
         * subscription signature.  This function is used by both
         * <code>on()</code> and <code>delegate()</code>.  The second parameter
         * indicates that the event is being subscribed via
         * <code>delegate()</code>.</p>
         *
         * <p>Implementations must remove extra arguments from the args list
         * before returning.  The required args for <code>on()</code>
         * subscriptions are</p>
         * <pre><code>[type, callback, target, context, argN...]</code></pre>
         *
         * <p>The required args for <code>delegate()</code>
         * subscriptions are</p>
         *
         * <pre><code>[type, callback, target, filter, context, argN...]</code></pre>
         *
         * <p>The return value from this function will be stored on the
         * subscription in the '_extra' property for reference elsewhere.</p>
         *
         * @method processArgs
         * @param args {Array} parmeters passed to Y.on(..) or Y.delegate(..)
         * @param delegate {Boolean} true if the subscription is from Y.delegate
         * @return {any}
         */
        processArgs: noop,

        /**
         * <p>Implementers MAY override this property.</p>
         *
         * <p>Whether to prevent multiple subscriptions to this event that are
         * classified as being the same.  By default, this means the subscribed
         * callback is the same function.  See the <code>subMatch</code>
         * method.  Setting this to true will impact performance for high volume
         * events.</p>
         *
         * @property preventDups
         * @type {Boolean}
         * @default false
         */
        //preventDups  : false,

        /**
         * <p>Implementers SHOULD provide this method definition.</p>
         *
         * Implementation logic for subscriptions done via <code>node.on(type,
         * fn)</code> or <code>Y.on(type, fn, target)</code>.  This
         * function should set up the monitor(s) that will eventually fire the
         * event.  Typically this involves subscribing to at least one DOM
         * event.  It is recommended to store detach handles from any DOM
         * subscriptions to make for easy cleanup in the <code>detach</code>
         * method.  Typically these handles are added to the <code>sub</code>
         * object.  Also for SyntheticEvents that leverage a single DOM
         * subscription under the hood, it is recommended to pass the DOM event
         * object to <code>notifier.fire(e)</code>.  (The event name on the
         * object will be updated).
         *
         * @method on
         * @param node {Node} the node the subscription is being applied to
         * @param sub {Subscription} the object to track this subscription
         * @param notifier {SyntheticEvent.Notifier} call notifier.fire(..) to
         *              trigger the execution of the subscribers
         */
        on: noop,

        /**
         * <p>Implementers SHOULD provide this method definition.</p>
         *
         * <p>Implementation logic for detaching subscriptions done via
         * <code>node.on(type, fn)</code>.  This function should clean up any
         * subscriptions made in the <code>on()</code> phase.</p>
         *
         * @method detach
         * @param node {Node} the node the subscription was applied to
         * @param sub {Subscription} the object tracking this subscription
         * @param notifier {SyntheticEvent.Notifier} the Notifier used to
         *              trigger the execution of the subscribers
         */
        detach: noop,

        /**
         * <p>Implementers SHOULD provide this method definition.</p>
         *
         * <p>Implementation logic for subscriptions done via
         * <code>node.delegate(type, fn, filter)</code> or
         * <code>Y.delegate(type, fn, container, filter)</code>.  Like with
         * <code>on()</code> above, this function should monitor the environment
         * for the event being fired, and trigger subscription execution by
         * calling <code>notifier.fire(e)</code>.</p>
         *
         * <p>This function receives a fourth argument, which is the filter
         * used to identify which Node's are of interest to the subscription.
         * The filter will be either a boolean function that accepts a target
         * Node for each hierarchy level as the event bubbles, or a selector
         * string.  To translate selector strings into filter functions, use
         * <code>Y.delegate.compileFilter(filter)</code>.</p>
         *
         * @method delegate
         * @param node {Node} the node the subscription is being applied to
         * @param sub {Subscription} the object to track this subscription
         * @param notifier {SyntheticEvent.Notifier} call notifier.fire(..) to
         *              trigger the execution of the subscribers
         * @param filter {String|Function} Selector string or function that
         *              accepts an event object and returns null, a Node, or an
         *              array of Nodes matching the criteria for processing.
         * @since 3.2.0
         */
        delegate       : noop,

        /**
         * <p>Implementers SHOULD provide this method definition.</p>
         *
         * <p>Implementation logic for detaching subscriptions done via
         * <code>node.delegate(type, fn, filter)</code> or
         * <code>Y.delegate(type, fn, container, filter)</code>.  This function
         * should clean up any subscriptions made in the
         * <code>delegate()</code> phase.</p>
         *
         * @method detachDelegate
         * @param node {Node} the node the subscription was applied to
         * @param sub {Subscription} the object tracking this subscription
         * @param notifier {SyntheticEvent.Notifier} the Notifier used to
         *              trigger the execution of the subscribers
         * @param filter {String|Function} Selector string or function that
         *              accepts an event object and returns null, a Node, or an
         *              array of Nodes matching the criteria for processing.
         * @since 3.2.0
         */
        detachDelegate : noop,

        /**
         * Sets up the boilerplate for detaching the event and facilitating the
         * execution of subscriber callbacks.
         *
         * @method _on
         * @param args {Array} array of arguments passed to
         *              <code>Y.on(...)</code> or <code>Y.delegate(...)</code>
         * @param delegate {Boolean} true if called from
         * <code>Y.delegate(...)</code>
         * @return {EventHandle} the detach handle for this subscription
         * @private
         * since 3.2.0
         */
        _on: function (args, delegate) {
            var handles  = [],
                originalArgs = args.slice(),
                extra    = this.processArgs(args, delegate),
                selector = args[2],
                method   = delegate ? 'delegate' : 'on',
                nodes, handle;

            // Can't just use Y.all because it doesn't support window (yet?)
            nodes = (isString(selector)) ?
                query(selector) :
                toArray(selector || Y.one(Y.config.win));

            if (!nodes.length && isString(selector)) {
                handle = Y.on('available', function () {
                    Y.mix(handle, Y[method].apply(Y, originalArgs), true);
                }, selector);

                return handle;
            }

            Y.Array.each(nodes, function (node) {
                var subArgs = args.slice(),
                    filter;

                node = Y.one(node);

                if (node) {
                    if (delegate) {
                        filter = subArgs.splice(3, 1)[0];
                    }

                    // (type, fn, el, thisObj, ...) => (fn, thisObj, ...)
                    subArgs.splice(0, 4, subArgs[1], subArgs[3]);

                    if (!this.preventDups ||
                        !this.getSubs(node, args, null, true))
                    {
                        handles.push(this._subscribe(node, method, subArgs, extra, filter));
                    }
                }
            }, this);

            return (handles.length === 1) ?
                handles[0] :
                new Y.EventHandle(handles);
        },

        /**
         * Creates a new Notifier object for use by this event's
         * <code>on(...)</code> or <code>delegate(...)</code> implementation
         * and register the custom event proxy in the DOM system for cleanup.
         *
         * @method _subscribe
         * @param node {Node} the Node hosting the event
         * @param method {String} "on" or "delegate"
         * @param args {Array} the subscription arguments passed to either
         *              <code>Y.on(...)</code> or <code>Y.delegate(...)</code>
         *              after running through <code>processArgs(args)</code> to
         *              normalize the argument signature
         * @param extra {any} Extra data parsed from
         *              <code>processArgs(args)</code>
         * @param filter {String|Function} the selector string or function
         *              filter passed to <code>Y.delegate(...)</code> (not
         *              present when called from <code>Y.on(...)</code>)
         * @return {EventHandle}
         * @private
         * @since 3.2.0
         */
        _subscribe: function (node, method, args, extra, filter) {
            var dispatcher = new Y.CustomEvent(this.type, this.publishConfig),
                handle     = dispatcher.on.apply(dispatcher, args),
                notifier   = new Notifier(handle, this.emitFacade),
                registry   = SyntheticEvent.getRegistry(node, this.type, true),
                sub        = handle.sub;

            sub.node   = node;
            sub.filter = filter;
            if (extra) {
                this.applyArgExtras(extra, sub);
            }

            Y.mix(dispatcher, {
                eventDef     : this,
                notifier     : notifier,
                host         : node,       // I forget what this is for
                currentTarget: node,       // for generating facades
                target       : node,       // for generating facades
                el           : node._node, // For category detach

                _delete      : SyntheticEvent._deleteSub
            }, true);

            handle.notifier = notifier;

            registry.register(handle);

            // Call the implementation's "on" or "delegate" method
            this[method](node, sub, notifier, filter);

            return handle;
        },

        /**
         * <p>Implementers MAY provide this method definition.</p>
         *
         * <p>Implement this function if you want extra data extracted during
         * processArgs to be propagated to subscriptions on a per-node basis.
         * That is to say, if you call <code>Y.on('xyz', fn, xtra, 'div')</code>
         * the data returned from processArgs will be shared
         * across the subscription objects for all the divs.  If you want each
         * subscription to receive unique information, do that processing
         * here.</p>
         *
         * <p>The default implementation adds the data extracted by processArgs
         * to the subscription object as <code>sub._extra</code>.</p>
         *
         * @method applyArgExtras
         * @param extra {any} Any extra data extracted from processArgs
         * @param sub {Subscription} the individual subscription
         */
        applyArgExtras: function (extra, sub) {
            sub._extra = extra;
        },

        /**
         * Removes the subscription(s) from the internal subscription dispatch
         * mechanism.  See <code>SyntheticEvent._deleteSub</code>.
         *
         * @method _detach
         * @param args {Array} The arguments passed to
         *                  <code>node.detach(...)</code>
         * @private
         * @since 3.2.0
         */
        _detach: function (args) {
            // Can't use Y.all because it doesn't support window (yet?)
            // TODO: Does Y.all support window now?
            var target = args[2],
                els    = (isString(target)) ?
                            query(target) : toArray(target),
                node, i, len, handles, j;

            // (type, fn, el, context, filter?) => (type, fn, context, filter?)
            args.splice(2, 1);

            for (i = 0, len = els.length; i < len; ++i) {
                node = Y.one(els[i]);

                if (node) {
                    handles = this.getSubs(node, args);

                    if (handles) {
                        for (j = handles.length - 1; j >= 0; --j) {
                            handles[j].detach();
                        }
                    }
                }
            }
        },

        /**
         * Returns the detach handles of subscriptions on a node that satisfy a
         * search/filter function.  By default, the filter used is the
         * <code>subMatch</code> method.
         *
         * @method getSubs
         * @param node {Node} the node hosting the event
         * @param args {Array} the array of original subscription args passed
         *              to <code>Y.on(...)</code> (before
         *              <code>processArgs</code>
         * @param filter {Function} function used to identify a subscription
         *              for inclusion in the returned array
         * @param first {Boolean} stop after the first match (used to check for
         *              duplicate subscriptions)
         * @return {EventHandle[]} detach handles for the matching subscriptions
         */
        getSubs: function (node, args, filter, first) {
            var registry = SyntheticEvent.getRegistry(node, this.type),
                handles  = [],
                allHandles, i, len, handle;

            if (registry) {
                allHandles = registry.handles;

                if (!filter) {
                    filter = this.subMatch;
                }

                for (i = 0, len = allHandles.length; i < len; ++i) {
                    handle = allHandles[i];
                    if (filter.call(this, handle.sub, args)) {
                        if (first) {
                            return handle;
                        } else {
                            handles.push(allHandles[i]);
                        }
                    }
                }
            }

            return handles.length && handles;
        },

        /**
         * <p>Implementers MAY override this to define what constitutes a
         * &quot;same&quot; subscription.  Override implementations should
         * consider the lack of a comparator as a match, so calling
         * <code>getSubs()</code> with no arguments will return all subs.</p>
         *
         * <p>Compares a set of subscription arguments against a Subscription
         * object to determine if they match.  The default implementation
         * compares the callback function against the second argument passed to
         * <code>Y.on(...)</code> or <code>node.detach(...)</code> etc.</p>
         *
         * @method subMatch
         * @param sub {Subscription} the existing subscription
         * @param args {Array} the calling arguments passed to
         *                  <code>Y.on(...)</code> etc.
         * @return {Boolean} true if the sub can be described by the args
         *                  present
         * @since 3.2.0
         */
        subMatch: function (sub, args) {
            // Default detach cares only about the callback matching
            return !args[1] || sub.fn === args[1];
        }
    }
}, true);

Y.SyntheticEvent = SyntheticEvent;

/**
 * <p>Defines a new event in the DOM event system.  Implementers are
 * responsible for monitoring for a scenario whereby the event is fired.  A
 * notifier object is provided to the functions identified below.  When the
 * criteria defining the event are met, call notifier.fire( [args] ); to
 * execute event subscribers.</p>
 *
 * <p>The first parameter is the name of the event.  The second parameter is a
 * configuration object which define the behavior of the event system when the
 * new event is subscribed to or detached from.  The methods that should be
 * defined in this configuration object are <code>on</code>,
 * <code>detach</code>, <code>delegate</code>, and <code>detachDelegate</code>.
 * You are free to define any other methods or properties needed to define your
 * event.  Be aware, however, that since the object is used to subclass
 * SyntheticEvent, you should avoid method names used by SyntheticEvent unless
 * your intention is to override the default behavior.</p>
 *
 * <p>This is a list of properties and methods that you can or should specify
 * in the configuration object:</p>
 *
 * <dl>
 *   <dt><code>on</code></dt>
 *       <dd><code>function (node, subscription, notifier)</code> The
 *       implementation logic for subscription.  Any special setup you need to
 *       do to create the environment for the event being fired--E.g. native
 *       DOM event subscriptions.  Store subscription related objects and
 *       state on the <code>subscription</code> object.  When the
 *       criteria have been met to fire the synthetic event, call
 *       <code>notifier.fire(e)</code>.  See Notifier's <code>fire()</code>
 *       method for details about what to pass as parameters.</dd>
 *
 *   <dt><code>detach</code></dt>
 *       <dd><code>function (node, subscription, notifier)</code> The
 *       implementation logic for cleaning up a detached subscription. E.g.
 *       detach any DOM subscriptions added in <code>on</code>.</dd>
 *
 *   <dt><code>delegate</code></dt>
 *       <dd><code>function (node, subscription, notifier, filter)</code> The
 *       implementation logic for subscription via <code>Y.delegate</code> or
 *       <code>node.delegate</code>.  The filter is typically either a selector
 *       string or a function.  You can use
 *       <code>Y.delegate.compileFilter(selectorString)</code> to create a
 *       filter function from a selector string if needed.  The filter function
 *       expects an event object as input and should output either null, a
 *       matching Node, or an array of matching Nodes.  Otherwise, this acts
 *       like <code>on</code> DOM event subscriptions.  Store subscription
 *       related objects and information on the <code>subscription</code>
 *       object.  When the criteria have been met to fire the synthetic event,
 *       call <code>notifier.fire(e)</code> as noted above.</dd>
 *
 *   <dt><code>detachDelegate</code></dt>
 *       <dd><code>function (node, subscription, notifier)</code> The
 *       implementation logic for cleaning up a detached delegate subscription.
 *       E.g. detach any DOM delegate subscriptions added in
 *       <code>delegate</code>.</dd>
 *
 *   <dt><code>publishConfig</code></dt>
 *       <dd>(Object) The configuration object that will be used to instantiate
 *       the underlying CustomEvent. See Notifier's <code>fire</code> method
 *       for details.</dd>
 *
 *   <dt><code>processArgs</code></dt
 *       <dd>
 *          <p><code>function (argArray, fromDelegate)</code> Optional method
 *          to extract any additional arguments from the subscription
 *          signature.  Using this allows <code>on</code> or
 *          <code>delegate</code> signatures like
 *          <code>node.on(&quot;hover&quot;, overCallback,
 *          outCallback)</code>.</p>
 *          <p>When processing an atypical argument signature, make sure the
 *          args array is returned to the normal signature before returning
 *          from the function.  For example, in the &quot;hover&quot; example
 *          above, the <code>outCallback</code> needs to be <code>splice</code>d
 *          out of the array.  The expected signature of the args array for
 *          <code>on()</code> subscriptions is:</p>
 *          <pre>
 *              <code>[type, callback, target, contextOverride, argN...]</code>
 *          </pre>
 *          <p>And for <code>delegate()</code>:</p>
 *          <pre>
 *              <code>[type, callback, target, filter, contextOverride, argN...]</code>
 *          </pre>
 *          <p>where <code>target</code> is the node the event is being
 *          subscribed for.  You can see these signatures documented for
 *          <code>Y.on()</code> and <code>Y.delegate()</code> respectively.</p>
 *          <p>Whatever gets returned from the function will be stored on the
 *          <code>subscription</code> object under
 *          <code>subscription._extra</code>.</p></dd>
 *   <dt><code>subMatch</code></dt>
 *       <dd>
 *           <p><code>function (sub, args)</code>  Compares a set of
 *           subscription arguments against a Subscription object to determine
 *           if they match.  The default implementation compares the callback
 *           function against the second argument passed to
 *           <code>Y.on(...)</code> or <code>node.detach(...)</code> etc.</p>
 *       </dd>
 * </dl>
 *
 * @method define
 * @param type {String} the name of the event
 * @param config {Object} the prototype definition for the new event (see above)
 * @param force {Boolean} override an existing event (use with caution)
 * @return {SyntheticEvent} the subclass implementation instance created to
 *              handle event subscriptions of this type
 * @static
 * @for Event
 * @since 3.1.0
 * @in event-synthetic
 */
Y.Event.define = function (type, config, force) {
    var eventDef, Impl, synth;

    if (type && type.type) {
        eventDef = type;
        force = config;
    } else if (config) {
        eventDef = Y.merge({ type: type }, config);
    }

    if (eventDef) {
        if (force || !Y.Node.DOM_EVENTS[eventDef.type]) {
            Impl = function () {
                SyntheticEvent.apply(this, arguments);
            };
            Y.extend(Impl, SyntheticEvent, eventDef);
            synth = new Impl();

            type = synth.type;

            Y.Node.DOM_EVENTS[type] = Y.Env.evt.plugins[type] = {
                eventDef: synth,

                on: function () {
                    return synth._on(toArray(arguments));
                },

                delegate: function () {
                    return synth._on(toArray(arguments), true);
                },

                detach: function () {
                    return synth._detach(toArray(arguments));
                }
            };

        }
    } else if (isString(type) || isArray(type)) {
        Y.Array.each(toArray(type), function (t) {
            Y.Node.DOM_EVENTS[t] = 1;
        });
    }

    return synth;
};


}, '3.4.1' ,{requires:['node-base', 'event-custom-complex']});
/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
YUI.add('event-mouseenter', function(Y) {

/**
 * <p>Adds subscription and delegation support for mouseenter and mouseleave
 * events.  Unlike mouseover and mouseout, these events aren't fired from child
 * elements of a subscribed node.</p>
 *
 * <p>This avoids receiving three mouseover notifications from a setup like</p>
 *
 * <pre><code>div#container > p > a[href]</code></pre>
 *
 * <p>where</p>
 *
 * <pre><code>Y.one('#container').on('mouseover', callback)</code></pre>
 *
 * <p>When the mouse moves over the link, one mouseover event is fired from
 * #container, then when the mouse moves over the p, another mouseover event is
 * fired and bubbles to #container, causing a second notification, and finally
 * when the mouse moves over the link, a third mouseover event is fired and
 * bubbles to #container for a third notification.</p>
 *
 * <p>By contrast, using mouseenter instead of mouseover, the callback would be
 * executed only once when the mouse moves over #container.</p>
 *
 * @module event
 * @submodule event-mouseenter
 */

var domEventProxies = Y.Env.evt.dom_wrappers,
    contains = Y.DOM.contains,
    toArray = Y.Array,
    noop = function () {},

    config = {
        proxyType: "mouseover",
        relProperty: "fromElement",

        _notify: function (e, property, notifier) {
            var el = this._node,
                related = e.relatedTarget || e[property];

            if (el !== related && !contains(el, related)) {
                notifier.fire(new Y.DOMEventFacade(e, el,
                    domEventProxies['event:' + Y.stamp(el) + e.type]));
            }
        },

        on: function (node, sub, notifier) {
            var el = Y.Node.getDOMNode(node),
                args = [
                    this.proxyType,
                    this._notify,
                    el,
                    null,
                    this.relProperty,
                    notifier];

            sub.handle = Y.Event._attach(args, { facade: false });
            // node.on(this.proxyType, notify, null, notifier);
        },

        detach: function (node, sub) {
            sub.handle.detach();
        },

        delegate: function (node, sub, notifier, filter) {
            var el = Y.Node.getDOMNode(node),
                args = [
                    this.proxyType,
                    noop,
                    el,
                    null,
                    notifier
                ];

            sub.handle = Y.Event._attach(args, { facade: false });
            sub.handle.sub.filter = filter;
            sub.handle.sub.relProperty = this.relProperty;
            sub.handle.sub._notify = this._filterNotify;
        },

        _filterNotify: function (thisObj, args, ce) {
            args = args.slice();
            if (this.args) {
                args.push.apply(args, this.args);
            }

            var currentTarget = Y.delegate._applyFilter(this.filter, args, ce),
                related = args[0].relatedTarget || args[0][this.relProperty],
                e, i, len, ret, ct;

            if (currentTarget) {
                currentTarget = toArray(currentTarget);
                
                for (i = 0, len = currentTarget.length && (!e || !e.stopped); i < len; ++i) {
                    ct = currentTarget[0];
                    if (!contains(ct, related)) {
                        if (!e) {
                            e = new Y.DOMEventFacade(args[0], ct, ce);
                            e.container = Y.one(ce.el);
                        }
                        e.currentTarget = Y.one(ct);

                        // TODO: where is notifier? args? this.notifier?
                        ret = args[1].fire(e);

                        if (ret === false) {
                            break;
                        }
                    }
                }
            }

            return ret;
        },

        detachDelegate: function (node, sub) {
            sub.handle.detach();
        }
    };

Y.Event.define("mouseenter", config, true);
Y.Event.define("mouseleave", Y.merge(config, {
    proxyType: "mouseout",
    relProperty: "toElement"
}), true);


}, '3.4.1' ,{requires:['event-synthetic']});
/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
YUI.add('event-resize', function(Y) {

/**
 * Adds a window resize event that has its behavior normalized to fire at the
 * end of the resize rather than constantly during the resize.
 * @module event
 * @submodule event-resize
 */


/**
 * Old firefox fires the window resize event once when the resize action
 * finishes, other browsers fire the event periodically during the
 * resize.  This code uses timeout logic to simulate the Firefox 
 * behavior in other browsers.
 * @event windowresize
 * @for YUI
 */

var domEventProxies = Y.Env.evt.dom_wrappers,
    win = Y.config.win,
    key = 'event:' + Y.stamp(win) + 'resizenative',
    config;
    

Y.Event.define('windowresize', {

    on: (Y.UA.gecko && Y.UA.gecko < 1.91) ?
        function (node, sub, notifier) {
            sub._handle = Y.Event._attach(['resize', function (e) {
                notifier.fire(
                    new Y.DOMEventFacade(e, win, domEventProxies[key]));
            }], { facade: false });
        } :
        function (node, sub, notifier) {
            // interval bumped from 40 to 100ms as of 3.4.1
            var delay = Y.config.windowResizeDelay || 100;

            sub._handle = Y.Event._attach(['resize', function (e) {
                if (sub._timer) {
                    sub._timer.cancel();
                }

                sub._timer = Y.later(delay, Y, function () {
                    notifier.fire(
                        new Y.DOMEventFacade(e, win, domEventProxies[key]));
                });
            }], { facade: false });
        },

    detach: function (node, sub) {
        if (sub._timer) {
            sub._timer.cancel();
        }
        sub._handle.detach();
    }
    // delegate methods not defined because this only works for window
    // subscriptions, so...yeah.
});


}, '3.4.1' ,{requires:['event-synthetic']});
YUI.add('moodle-block_navigation-navigation', function(Y){

/**
 * A 'actionkey' Event to help with Y.delegate().
 * The event consists of the left arrow, right arrow, enter and space keys.
 * More keys can be mapped to action meanings.
 * actions: collapse , expand, toggle, enter.
 *
 * This event is delegated to branches in the navigation tree.
 * The on() method to subscribe allows specifying the desired trigger actions as JSON.
 *
 * Todo: This could be centralised, a similar Event is defined in blocks/dock.js
 */
Y.Event.define("actionkey", {
   // Webkit and IE repeat keydown when you hold down arrow keys.
    // Opera links keypress to page scroll; others keydown.
    // Firefox prevents page scroll via preventDefault() on either
    // keydown or keypress.
    _event: (Y.UA.webkit || Y.UA.ie) ? 'keydown' : 'keypress',

    _keys: {
        //arrows
        '37': 'collapse',
        '39': 'expand',
        //(@todo: lrt/rtl/M.core_dock.cfg.orientation decision to assign arrow to meanings)
        '32': 'toggle',
        '13': 'enter'
    },

    _keyHandler: function (e, notifier, args) {
        if (!args.actions) {
            var actObj = {collapse:true, expand:true, toggle:true, enter:true};
        } else {
            var actObj = args.actions;
        }
        if (this._keys[e.keyCode] && actObj[this._keys[e.keyCode]]) {
            e.action = this._keys[e.keyCode];
            notifier.fire(e);
        }
    },

    on: function (node, sub, notifier) {
        // subscribe to _event and ask keyHandler to handle with given args[0] (the desired actions).
        if (sub.args == null) {
            //no actions given
            sub._detacher = node.on(this._event, this._keyHandler,this, notifier, {actions:false});
        } else {
            sub._detacher = node.on(this._event, this._keyHandler,this, notifier, sub.args[0]);
        }
    },

    detach: function (node, sub, notifier) {
        //detach our _detacher handle of the subscription made in on()
        sub._detacher.detach();
    },

    delegate: function (node, sub, notifier, filter) {
        // subscribe to _event and ask keyHandler to handle with given args[0] (the desired actions).
        if (sub.args == null) {
            //no actions given
            sub._delegateDetacher = node.delegate(this._event, this._keyHandler,filter, this, notifier, {actions:false});
        } else {
            sub._delegateDetacher = node.delegate(this._event, this._keyHandler,filter, this, notifier, sub.args[0]);
        }
    },

    detachDelegate: function (node, sub, notifier) {
        sub._delegateDetacher.detach();
    }
});

var EXPANSIONLIMIT_EVERYTHING = 0,
    EXPANSIONLIMIT_COURSE     = 20,
    EXPANSIONLIMIT_SECTION    = 30,
    EXPANSIONLIMIT_ACTIVITY   = 40;


/**
 * Navigation tree class.
 *
 * This class establishes the tree initially, creating expandable branches as
 * required, and delegating the expand/collapse event.
 */
var TREE = function(config) {
    TREE.superclass.constructor.apply(this, arguments);
}
TREE.prototype = {
    /**
     * The tree's ID, normally its block instance id.
     */
    id : null,
    /**
     * Initialise the tree object when its first created.
     */
    initializer : function(config) {
        this.id = config.id;

        var node = Y.one('#inst'+config.id);

        // Can't find the block instance within the page
        if (node === null) {
            return;
        }

        // Delegate event to toggle expansion
        var self = this;
        Y.delegate('click', function(e){self.toggleExpansion(e);}, node.one('.block_tree'), '.tree_item.branch');
        Y.delegate('actionkey', function(e){self.toggleExpansion(e);}, node.one('.block_tree'), '.tree_item.branch');

        // Gather the expandable branches ready for initialisation.
        var expansions = [];
        if (config.expansions) {
            expansions = config.expansions;
        } else if (window['navtreeexpansions'+config.id]) {
            expansions = window['navtreeexpansions'+config.id];
        }
        // Establish each expandable branch as a tree branch.
        for (var i in expansions) {
            new BRANCH({
                tree:this,
                branchobj:expansions[i],
                overrides : {
                    expandable : true,
                    children : [],
                    haschildren : true
                }
            }).wire();
            M.block_navigation.expandablebranchcount++;
        }

        // Call the generic blocks init method to add all the generic stuff
        if (this.get('candock')) {
            this.initialise_block(Y, node);
        }
    },
    /**
     * This is a callback function responsible for expanding and collapsing the
     * branches of the tree. It is delegated to rather than multiple event handles.
     */
    toggleExpansion : function(e) {
        // First check if they managed to click on the li iteslf, then find the closest
        // LI ancestor and use that

        if (e.target.test('a') && (e.keyCode == 0 || e.keyCode == 13)) {
            // A link has been clicked (or keypress is 'enter') don't fire any more events just do the default.
            e.stopPropagation();
            return;
        }

        // Makes sure we can get to the LI containing the branch.
        var target = e.target;
        if (!target.test('li')) {
            target = target.ancestor('li')
        }
        if (!target) {
            return;
        }

        // Toggle expand/collapse providing its not a root level branch.
        if (!target.hasClass('depth_1')) {
            if (e.type == 'actionkey') {
                switch (e.action) {
                    case 'expand' :
                        target.removeClass('collapsed');
                        break;
                    case 'collapse' :
                        target.addClass('collapsed');
                        break;
                    default :
                        target.toggleClass('collapsed');
                }
                e.halt();
            } else {
                target.toggleClass('collapsed');
            }
        }

        // If the accordian feature has been enabled collapse all siblings.
        if (this.get('accordian')) {
            target.siblings('li').each(function(){
                if (this.get('id') !== target.get('id') && !this.hasClass('collapsed')) {
                    this.addClass('collapsed');
                }
            });
        }

        // If this block can dock tell the dock to resize if required and check
        // the width on the dock panel in case it is presently in use.
        if (this.get('candock')) {
            M.core_dock.resize();
            var panel = M.core_dock.getPanel();
            if (panel.visible) {
                panel.correctWidth();
            }
        }
    }
}
// The tree extends the YUI base foundation.
Y.extend(TREE, Y.Base, TREE.prototype, {
    NAME : 'navigation-tree',
    ATTRS : {
        instance : {
            value : null
        },
        candock : {
            validator : Y.Lang.isBool,
            value : false
        },
        accordian : {
            validator : Y.Lang.isBool,
            value : false
        },
        expansionlimit : {
            value : 0,
            setter : function(val) {
                return parseInt(val);
            }
        }
    }
});
if (M.core_dock && M.core_dock.genericblock) {
    Y.augment(TREE, M.core_dock.genericblock);
}

/**
 * The tree branch class.
 * This class is used to manage a tree branch, in particular its ability to load
 * its contents by AJAX.
 */
var BRANCH = function(config) {
    BRANCH.superclass.constructor.apply(this, arguments);
}
BRANCH.prototype = {
    /**
     * The node for this branch (p)
     */
    node : null,
    /**
     * A reference to the ajax load event handlers when created.
     */
    event_ajaxload : null,
    event_ajaxload_actionkey : null,
    /**
     * Initialises the branch when it is first created.
     */
    initializer : function(config) {
        if (config.branchobj !== null) {
            // Construct from the provided xml
            for (var i in config.branchobj) {
                this.set(i, config.branchobj[i]);
            }
            var children = this.get('children');
            this.set('haschildren', (children.length > 0));
        }
        if (config.overrides !== null) {
            // Construct from the provided xml
            for (var i in config.overrides) {
                this.set(i, config.overrides[i]);
            }
        }
        // Get the node for this branch
        this.node = Y.one('#', this.get('id'));
        // Now check whether the branch is not expandable because of the expansionlimit
        var expansionlimit = this.get('tree').get('expansionlimit');
        var type = this.get('type');
        if (expansionlimit != EXPANSIONLIMIT_EVERYTHING &&  type >= expansionlimit && type <= EXPANSIONLIMIT_ACTIVITY) {
            this.set('expandable', false);
            this.set('haschildren', false);
        }
    },
    /**
     * Draws the branch within the tree.
     *
     * This function creates a DOM structure for the branch and then injects
     * it into the navigation tree at the correct point.
     */
    draw : function(element) {

        var isbranch = (this.get('expandable') || this.get('haschildren'));
        var branchli = Y.Node.create('<li></li>');
        var link = this.get('link');
        var branchp = Y.Node.create('<p class="tree_item"></p>').setAttribute('id', this.get('id'));
        if (!link) {
            //add tab focus if not link (so still one focus per menu node).
            // it was suggested to have 2 foci. one for the node and one for the link in MDL-27428.
            branchp.setAttribute('tabindex', '0');
        }
        if (isbranch) {
            branchli.addClass('collapsed').addClass('contains_branch');
            branchp.addClass('branch');
        }

        // Prepare the icon, should be an object representing a pix_icon
        var branchicon = false;
        var icon = this.get('icon');
        if (icon && (!isbranch || this.get('type') == 40)) {
            branchicon = Y.Node.create('<img alt="" />');
            branchicon.setAttribute('src', M.util.image_url(icon.pix, icon.component));
            branchli.addClass('item_with_icon');
            if (icon.alt) {
                branchicon.setAttribute('alt', icon.alt);
            }
            if (icon.title) {
                branchicon.setAttribute('title', icon.title);
            }
            if (icon.classes) {
                for (var i in icon.classes) {
                    branchicon.addClass(icon.classes[i]);
                }
            }
        }

        if (!link) {
            if (branchicon) {
                branchp.appendChild(branchicon);
            }
            branchp.append(this.get('name'));
        } else {
            var branchlink = Y.Node.create('<a title="'+this.get('title')+'" href="'+link+'"></a>');
            if (branchicon) {
                branchlink.appendChild(branchicon);
            }
            branchlink.append(this.get('name'));
            if (this.get('hidden')) {
                branchlink.addClass('dimmed');
            }
            branchp.appendChild(branchlink);
        }

        branchli.appendChild(branchp);
        element.appendChild(branchli);
        this.node = branchp;
        return this;
    },
    /**
     * Attaches required events to the branch structure.
     */
    wire : function() {
        this.node = this.node || Y.one('#'+this.get('id'));
        if (!this.node) {
            return false;
        }
        if (this.get('expandable')) {
            this.event_ajaxload = this.node.on('ajaxload|click', this.ajaxLoad, this);
            this.event_ajaxload_actionkey = this.node.on('actionkey', this.ajaxLoad, this);
        }
        return this;
    },
    /**
     * Gets the UL element that children for this branch should be inserted into.
     */
    getChildrenUL : function() {
        var ul = this.node.next('ul');
        if (!ul) {
            ul = Y.Node.create('<ul></ul>');
            this.node.ancestor().append(ul);
        }
        return ul;
    },
    /**
     * Load the content of the branch via AJAX.
     *
     * This function calls ajaxProcessResponse with the result of the AJAX
     * request made here.
     */
    ajaxLoad : function(e) {
        if (e.type == 'actionkey' && e.action != 'enter') {
            e.halt();
        } else {
            e.stopPropagation();
        }
        if (e.type = 'actionkey' && e.action == 'enter' && e.target.test('A')) {
            this.event_ajaxload_actionkey.detach();
            this.event_ajaxload.detach();
            return true; // no ajaxLoad for enter
        }

        if (this.node.hasClass('loadingbranch')) {
            return true;
        }

        this.node.addClass('loadingbranch');

        var params = {
            elementid : this.get('id'),
            id : this.get('key'),
            type : this.get('type'),
            sesskey : M.cfg.sesskey,
            instance : this.get('tree').get('instance')
        };

        Y.io(M.cfg.wwwroot+'/lib/ajax/getnavbranch.php', {
            method:'POST',
            data:  build_querystring(params),
            on: {
                complete: this.ajaxProcessResponse
            },
            context:this
        });
        return true;
    },
    /**
     * Processes an AJAX request to load the content of this branch through
     * AJAX.
     */
    ajaxProcessResponse : function(tid, outcome) {
        this.node.removeClass('loadingbranch');
        this.event_ajaxload.detach();
        this.event_ajaxload_actionkey.detach();
        try {
            var object = Y.JSON.parse(outcome.responseText);
            if (object.children && object.children.length > 0) {
                var coursecount = 0;
                for (var i in object.children) {
                    if (typeof(object.children[i])=='object') {
                        if (object.children[i].type == 20) {
                            coursecount++;
                        }
                        this.addChild(object.children[i]);
                    }
                }
                if (this.get('type') == 10 && coursecount >= M.block_navigation.courselimit) {
                    this.addViewAllCoursesChild(this);
                }
                this.get('tree').toggleExpansion({target:this.node});
                return true;
            }
        } catch (ex) {
            // If we got here then there was an error parsing the result
        }
        // The branch is empty so class it accordingly
        this.node.replaceClass('branch', 'emptybranch');
        return true;
    },
    /**
     * Turns the branch object passed to the method into a proper branch object
     * and then adds it as a child of this branch.
     */
    addChild : function(branchobj) {
        // Make the new branch into an object
        var branch = new BRANCH({tree:this.get('tree'), branchobj:branchobj});
        if (branch.draw(this.getChildrenUL())) {
            branch.wire();
            var count = 0, i, children = branch.get('children');
            for (i in children) {
                // Add each branch to the tree
                if (children[i].type == 20) {
                    count++;
                }
                if (typeof(children[i])=='object') {
                    branch.addChild(children[i]);
                }
            }
            if (branch.get('type') == 10 && count >= M.block_navigation.courselimit) {
                this.addViewAllCoursesChild(branch);
            }
        }
        return true;
    },

    /**
     * Add a link to view all courses in a category
     */
    addViewAllCoursesChild: function(branch) {
        branch.addChild({
            name : M.str.moodle.viewallcourses,
            title : M.str.moodle.viewallcourses,
            link : M.cfg.wwwroot+'/course/category.php?id='+branch.get('key'),
            haschildren : false,
            icon : {'pix':"i/navigationitem",'component':'moodle'}
        });
    }
}
Y.extend(BRANCH, Y.Base, BRANCH.prototype, {
    NAME : 'navigation-branch',
    ATTRS : {
        tree : {
            validator : Y.Lang.isObject
        },
        name : {
            value : '',
            validator : Y.Lang.isString,
            setter : function(val) {
                return val.replace(/\n/g, '<br />');
            }
        },
        title : {
            value : '',
            validator : Y.Lang.isString
        },
        id : {
            value : '',
            validator : Y.Lang.isString,
            getter : function(val) {
                if (val == '') {
                    val = 'expandable_branch_'+M.block_navigation.expandablebranchcount;
                    M.block_navigation.expandablebranchcount++;
                }
                return val;
            }
        },
        key : {
            value : null
        },
        type : {
            value : null
        },
        link : {
            value : false
        },
        icon : {
            value : false,
            validator : Y.Lang.isObject
        },
        expandable : {
            value : false,
            validator : Y.Lang.isBool
        },
        hidden : {
            value : false,
            validator : Y.Lang.isBool
        },
        haschildren : {
            value : false,
            validator : Y.Lang.isBool
        },
        children : {
            value : [],
            validator : Y.Lang.isArray
        }
    }
});

/**
 * This namespace will contain all of the contents of the navigation blocks
 * global navigation and settings.
 * @namespace
 */
M.block_navigation = M.block_navigation || {
    /** The number of expandable branches in existence */
    expandablebranchcount:1,
    courselimit : 20,
    instance : null,
    /**
     * Add new instance of navigation tree to tree collection
     */
    init_add_tree:function(properties) {
        if (properties.courselimit) {
            this.courselimit = properties.courselimit;
        }
        if (M.core_dock) {
            M.core_dock.init(Y);
        }
        new TREE(properties);
    }
};

}, '@VERSION@', {requires:['base', 'core_dock', 'io-base', 'node', 'dom', 'event-custom', 'event-delegate', 'json-parse']});
