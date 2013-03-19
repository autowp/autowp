function Class() { };

Class.prototype.construct = function() { };

Class.extend = function(def) {
    var classDef = function() {
        if (arguments[0] !== Class) {
            this.construct.apply(this, arguments);
        }
    };
    var proto = new this(Class);
    var superClass = this.prototype;
    for (var n in def) {
        var item = def[n];
        if (item instanceof Function) item.$ = superClass;
                else classDef[n] = item;
        proto[n] = item;
    }
    classDef.prototype = proto;
    classDef.extend = this.extend;
    return classDef;
};