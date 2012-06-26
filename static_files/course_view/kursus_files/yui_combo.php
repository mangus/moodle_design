/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
/*
	TODO will need to remove settings on HTML since we can't namespace it.
	TODO with the prefix, should I group by selector or property for weight savings?
*/
html{
	color:#000;
	background:#FFF;
}
/*
	TODO remove settings on BODY since we can't namespace it.
*/
/*
	TODO test putting a class on HEAD.
		- Fails on FF. 
*/
body,
div,
dl,
dt,
dd,
ul,
ol,
li,
h1,
h2,
h3,
h4,
h5,
h6,
pre,
code,
form,
fieldset,
legend,
input,
textarea,
p,
blockquote,
th,
td {
	margin:0;
	padding:0;
}
table {
	border-collapse:collapse;
	border-spacing:0;
}
fieldset,
img {
	border:0;
}
/*
	TODO think about hanlding inheritence differently, maybe letting IE6 fail a bit...
*/
address,
caption,
cite,
code,
dfn,
em,
strong,
th,
var {
	font-style:normal;
	font-weight:normal;
}

ol,
ul {
	list-style:none;
}

caption,
th {
	text-align:left;
}
h1,
h2,
h3,
h4,
h5,
h6 {
	font-size:100%;
	font-weight:normal;
}
q:before,
q:after {
	content:'';
}
abbr,
acronym {
	border:0;
	font-variant:normal;
}
/* to preserve line-height and selector appearance */
sup {
	vertical-align:text-top;
}
sub {
	vertical-align:text-bottom;
}
input,
textarea,
select {
	font-family:inherit;
	font-size:inherit;
	font-weight:inherit;
}
/*to enable resizing for IE*/
input,
textarea,
select {
	*font-size:100%;
}
/*because legend doesn't inherit in IE */
legend {
	color:#000;
}
/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
/**
 * Percents could work for IE, but for backCompat purposes, we are using keywords.
 * x-small is for IE6/7 quirks mode.
 */
body {
	font:13px/1.231 arial,helvetica,clean,sans-serif;
	*font-size:small; /* for IE */
	*font:x-small; /* for IE in quirks mode */
}

/**
 * Nudge down to get to 13px equivalent for these form elements
 */ 
select,
input,
button,
textarea {
	font:99% arial,helvetica,clean,sans-serif;
}

/**
 * To help tables remember to inherit
 */
table {
	font-size:inherit;
	font:100%;
}

/**
 * Bump up IE to get to 13px equivalent for these fixed-width elements
 */
pre,
code,
kbd,
samp,
tt {
	font-family:monospace;
	*font-size:108%;
	line-height:100%;
}
/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
.yui3-g {
    letter-spacing: -0.31em; /* webkit: collapse white-space between units */
    *letter-spacing: normal; /* reset IE < 8 */
    word-spacing: -0.43em; /* IE < 8 && gecko: collapse white-space between units */
}

.yui3-u,
.yui3-u-1,
.yui3-u-1-2,
.yui3-u-1-3,
.yui3-u-2-3,
.yui3-u-1-4,
.yui3-u-3-4,
.yui3-u-1-5,
.yui3-u-2-5,
.yui3-u-3-5,
.yui3-u-4-5,
.yui3-u-1-6,
.yui3-u-5-6,
.yui3-u-1-8,
.yui3-u-3-8,
.yui3-u-5-8,
.yui3-u-7-8,
.yui3-u-1-12,
.yui3-u-5-12,
.yui3-u-7-12,
.yui3-u-11-12,
.yui3-u-1-24,
.yui3-u-5-24,
.yui3-u-7-24,
.yui3-u-11-24,
.yui3-u-13-24,
.yui3-u-17-24,
.yui3-u-19-24,
.yui3-u-23-24 {
    display: inline-block;
    zoom: 1; *display: inline; /* IE < 8: fake inline-block */
    letter-spacing: normal;
    word-spacing: normal;
    vertical-align: top;
}

.yui3-u-1 {
    display: block;
}

.yui3-u-1-2 {
    width: 50%;
}

.yui3-u-1-3 {
    width: 33.33333%;
}

.yui3-u-2-3 {
    width: 66.66666%;
}

.yui3-u-1-4 {
    width: 25%;
}

.yui3-u-3-4 {
    width: 75%;
}

.yui3-u-1-5 {
    width: 20%;
}

.yui3-u-2-5 {
    width: 40%;
}

.yui3-u-3-5 {
    width: 60%;
}

.yui3-u-4-5 {
    width: 80%;
}

.yui3-u-1-6 {
    width: 16.656%;
}

.yui3-u-5-6 {
    width: 83.33%;
}

.yui3-u-1-8 {
    width: 12.5%;
}

.yui3-u-3-8 {
    width: 37.5%;
}

.yui3-u-5-8 {
    width: 62.5%;
}

.yui3-u-7-8 {
    width: 87.5%;
}

.yui3-u-1-12 {
    width: 8.3333%;
}

.yui3-u-5-12 {
    width: 41.6666%;
}

.yui3-u-7-12 {
    width: 58.3333%;
}

.yui3-u-11-12 {
    width: 91.6666%;
}

.yui3-u-1-24 {
    width: 4.1666%;
}

.yui3-u-5-24 {
    width: 20.8333%;
}

.yui3-u-7-24 {
    width: 29.1666%;
}

.yui3-u-11-24 {
    width: 45.8333%;
}

.yui3-u-13-24 {
    width: 54.1666%;
}

.yui3-u-17-24 {
    width: 70.8333%;
}

.yui3-u-19-24 {
    width: 79.1666%;
}

.yui3-u-23-24 {
    width: 95.8333%;
}
/*
YUI 3.4.1 (build 4118)
Copyright 2011 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/
/* base.css, part of YUI's CSS Foundation */
h1 {
	/*18px via YUI Fonts CSS foundation*/
	font-size:138.5%;  
}
h2 {
	/*16px via YUI Fonts CSS foundation*/
	font-size:123.1%; 
}
h3 {
	/*14px via YUI Fonts CSS foundation*/
	font-size:108%;  
}
h1,h2,h3 {
	/* top & bottom margin based on font size */
	margin:1em 0;
}
h1,h2,h3,h4,h5,h6,strong {
	/*bringing boldness back to headers and the strong element*/
	font-weight:bold; 
}
abbr,acronym {
	/*indicating to users that more info is available */
	border-bottom:1px dotted #000;
	cursor:help;
} 
em {
	/*bringing italics back to the em element*/
	font-style:italic;
}
blockquote,ul,ol,dl {
	/*giving blockquotes and lists room to breath*/
	margin:1em;
}
ol,ul,dl {
	/*bringing lists on to the page with breathing room */
	margin-left:2em;
}
ol {
	/*giving OL's LIs generated numbers*/
	list-style: decimal outside;	
}
ul {
	/*giving UL's LIs generated disc markers*/
	list-style: disc outside;
}
dl dd {
	/*providing spacing for definition terms*/
	margin-left:1em;
}
th,td {
	/*borders and padding to make the table readable*/
	border:1px solid #000;
	padding:.5em;
}
th {
	/*distinguishing table headers from data cells*/
	font-weight:bold;
	text-align:center;
}
caption {
	/*coordinated margin to match cell's padding*/
	margin-bottom:.5em;
	/*centered so it doesn't blend in to other content*/
	text-align:center;
}
p,fieldset,table,pre {
	/*so things don't run into each other*/
	margin-bottom:1em;
}
/* setting a consistent width, 160px; 
   control of type=file still not possible */
input[type=text],input[type=password],textarea{width:12.25em;*width:11.9em;}
