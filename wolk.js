var Storage = function(namespace, key, url) {
	this.apiKey = key;
	this.apiUrl = url;
	
	this.namespace = namespace;
	
	this.isDirty = false;
	this.scheduledSync = null;
	
	this.listeners = {};
	
	window.addEventListener('storage',
		this.storageEvent.bind(this),
		false);
	
	window.addEventListener('beforeunload',
		(function() { if (this.isDirty) this.synchronize(); }).bind(this),
		false);
	
	this.scheduleNextSynchronize(1000);
}

Storage.prototype = {
	
	key: function() {
		return this.namespace + '.' + Array.prototype.slice.apply(arguments).join('.');
	},

	localKey: function(fullKey) {
		// @TODO fix this so namespace can contain regular expression operators
		// without breaking this expression.
		return fullKey.replace(new RegExp('^' + this.namespace + '\.'), '');
	},
	
	inNamespace: function(key) {
		return key.substr(0, this.namespace.length) == this.namespace;
	},
	
	isHidden: function(key) {
		return key.substr(this.namespace.length + 1, 2) == '__';
	},
	
	encode: JSON.stringify,
	
	decode: JSON.parse,
	
	get: function(key) {
		if (!this.exists(key))
			return null;
		
		try {
			data = window.localStorage[this.key(key)].split(';', 2);
			return data && data.length >= 2 ? this.decode(data[1]) : null;
		} catch (e) {
			return null;
		}
	},
	
	set: function(key, value) {
		this.__set(this.key(key), value, new Date());
		this.emit(key, value);
		this.markDirty();
	},
	
	__set: function(key, value, timestamp) {
		console.log('__set', key, value, timestamp.getTime());
		window.localStorage[key] = [timestamp.getTime(), this.encode(value)].join(';');
	},
	
	__parseDate: function(datestring) {
		var d;
		if (d = datestring.match(/^(\d{4})[\/\.\-](\d{2})[\/\.\-](\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?Z$/))
			return new Date(Date.UTC(d[1], d[2] - 1, d[3], d[4], d[5], d[6], d[7]));
		else
			return new Date(datestring); // lets hope Date can make soup out of it.
	},
	
	remove: function(key) {
		this.set(key, null);
	},
	
	exists: function(key) {
		return typeof window.localStorage[this.key(key)] != 'undefined';
	},
	
	isNull: function(key) {
		return this.get(key) === null;
	},
	
	touch: function(key) {
		this.set(key, this.get(key));
	},
	
	lastModified: function(key) {
		if (!this.exists(key))
			return null;
		
		data = window.localStorage[this.key(key)].split(';', 2);
		//console.log(data[0], data && data.length == 2);
		return data && data.length == 2 ? new Date(parseInt(data[0])) : null;
	},
	
	pruneNullValues: function() {
		for (var key in window.localStorage)
			if (this.inNamespace(key) && !this.isHidden(key) && this.isNull(this.localKey(key)))
				window.localStorage.removeItem(key);
	},
	
	find: function(prefix) {
		var hits = {}
		
		for (var key in window.localStorage)
		{
			if (!this.inNamespace(key) || this.isHidden(key))
				continue;
			
			var local_key = this.localKey(key);
			
			if (this.isNull(local_key))
				continue;
			
			if (!prefix || local_key.substring(0, prefix.length) != prefix)
				hits[local_key] = this.get(local_key)
		}
		
		return hits;
	},
	
	connect: function(key, callback) {
		if (this.listeners[key])
			this.listeners[key].push(callback);
		else
			this.listeners[key] = [callback];
	},
	
	disconnect: function(key, callback) {
		if (typeof this.listeners[key] === 'undefined')
			return;
		
		this.listeners[key] = this.listeners[key].filter(function(listener) {
			return listener != callback;
		});
	},
	
	emit: function(key, value) {
		var listeners = this.listeners[key] || [];
		
		for (var i = 0; i < listeners.length; ++i)
		{
			try {
				listeners[i](value, key);
			} catch(e) {
				console.log('Exception from listener for ' + key, e);
			}
		}
	},

	markDirty: function() {
		this.isDirty = true;
		this.scheduleNextSynchronize(200);
	},
	
	storageEvent: function(e) {
		if (this.inNamespace(e.key))
		{
			var local_key = this.localKey(e.key);
			this.emit(local_key, this.get(local_key));
		}
	},
	
	scheduleNextSynchronize: function(timeout) {
		clearTimeout(this.scheduledSync);
		this.scheduledSync = setTimeout(
			this.runScheduledSynchronize.bind(this),
			timeout);
	},
	
	runScheduledSynchronize: function() {
		this.synchronize(false, this.scheduleNextSynchronize.bind(this, 15000));
	},

	synchronize: function(forceCompleteSync, callback) {
		if (!this.apiKey || !this.apiUrl)
			return false;
		
		var syncLock = window.localStorage[this.key('__sync_lock')];
		
		if (syncLock && new Date() - new Date(syncLock) < 5000)
			return false;
		
		var self = this;
		
		var lock = function() {
			window.localStorage[self.key('__sync_lock')] = new Date();
		}
		
		var unlock = function() {
			window.localStorage.removeItem(self.key('__sync_lock'));
		}
		
		var markUpdated = function() {
			window.localStorage[self.key('__last_sync')] = new Date();
		}
		
		lock();
		
		var lastSync = !forceCompleteSync && window.localStorage[this.key('__last_sync')]
			? new Date(window.localStorage[this.key('__last_sync')])
			: null;
		
		var pairsToSync = [];
	
		for (var key in localStorage) {
			//console.log("Evaluating", key, this.inNamespace(key), this.isHidden(this.localKey(key)), this.lastModified(this.localKey(key)), lastSync);
		
			if (!this.inNamespace(key))
				continue;
			
			var localKey = this.localKey(key);
			
			if (this.isHidden(localKey))
				continue;
			
			if (this.lastModified(localKey) > lastSync)
				pairsToSync.push({
					k: key,
					v: this.get(localKey),
					m: this.lastModified(localKey)
				});
		}
		
		var apiUrl = this.apiUrl
			+ '?api_key=' + encodeURIComponent(this.apiKey)
			+ (lastSync
				? '&since=' + encodeURIComponent('@' + Math.floor(lastSync.getUTCTime() / 1000))
				: '')
			+ ('&' + [this.namespace].map(function(namespace) { // voor later
						return 'namespaces[]='+encodeURIComponent(namespace);
					}).join('&'));
		
		var method = pairsToSync.length > 0
			? 'POST'
			: 'GET';
		
		var data = pairsToSync.length > 0
			? JSON.stringify(pairsToSync)
			: null;
		
		var request = new XMLHttpRequest();
		request.open(method, apiUrl, true);
		
		if (method == 'POST')
			request.setRequestHeader('Content-Type', 'application/json');
		
		request.onload = function() {
			try {
				if(request.status == 500)
					throw Error(request.responseText);
				
				var pairsToUpdate = JSON.parse(request.responseText);
				
				pairsToUpdate.forEach(function(pair) {
					self.__set(pair.k, pair.v, self.__parseDate(pair.m));
					self.emit(self.localKey(pair.k), pair.v);
				});
				
				markUpdated();
				
				self.pruneNullValues();
			}
			catch (e) {
				console.log('sync read failed', e);
			}
			finally {
				unlock();
				
				if (callback)
					callback();
			}
		}
		
		request.onerror = function() {
			unlock();
			
			if (callback)
				callback();
		}
		
		request.send(data);
		
		return true;
	}
}