// Backward-compatible service-worker entry point.
//
// Older AcadFlow installations may still have clients registered against
// `/sw.js`. Keep a single service-worker implementation so those clients get
// the same cache/privacy/performance behavior as `/serviceworker.js` instead
// of running the retired `uniflow-v1` worker.
importScripts('/serviceworker.js');
