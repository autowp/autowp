/**
 * @license RequireJS domReady 2.0.1 Copyright (c) 2010-2012, The Dojo Foundation All Rights Reserved.
 * Available via the MIT or new BSD license.
 * see: http://github.com/requirejs/domReady for details
 */

define([],function(){function u(e){var t;for(t=0;t<e.length;t+=1)e[t](s)}function a(){var e=o;i&&e.length&&(o=[],u(e))}function f(){i||(i=!0,n&&clearInterval(n),a())}function c(e){return i?e(s):o.push(e),c}var e,t,n,r=typeof window!="undefined"&&window.document,i=!r,s=r?document:null,o=[];if(r){if(document.addEventListener)document.addEventListener("DOMContentLoaded",f,!1),window.addEventListener("load",f,!1);else if(window.attachEvent){window.attachEvent("onload",f),t=document.createElement("div");try{e=window.frameElement===null}catch(l){}t.doScroll&&e&&window.external&&(n=setInterval(function(){try{t.doScroll(),f()}catch(e){}},30))}document.readyState==="complete"&&f()}return c.version="2.0.1",c.load=function(e,t,n,r){r.isBuild?n(null):c(n)},c});