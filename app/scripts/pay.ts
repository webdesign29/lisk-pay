// Enable chromereload by uncommenting this line:
import 'chromereload/devonly'

if(typeof chrome.runtime !== 'undefined') {

    chrome.runtime.onInstalled.addListener((details) => {
        console.log('previousVersion', details.previousVersion);
    //   chrome.tabs.create({ url: 'pages/pay.html' });
    });
    
    chrome.browserAction.setBadgeText({
        text: `'Lisk`
    });
    
}
console.log('Lisk Pay jordan');