@extends('layouts.app')

@section('content')
    <style>
        .google-btn {
            box-shadow: 0px 0px 3px rgba(0, 0, 0, 0.084), 0px 2px 3px rgba(0, 0, 0, 0.168) !important;
            border-radius: 10px !important;
        }
    </style>
    <div class="d-flex justify-content-end">
        <button onclick="handleAuthClick()" class="btn py-2 fw-500 google-btn" id="authorize_button">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" preserveAspectRatio="xMidYMid"
                viewBox="0 0 256 262" id="google">
                <path fill="#4285F4"
                    d="M255.878 133.451c0-10.734-.871-18.567-2.756-26.69H130.55v48.448h71.947c-1.45 12.04-9.283 30.172-26.69 42.356l-.244 1.622 38.755 30.023 2.685.268c24.659-22.774 38.875-56.282 38.875-96.027">
                </path>
                <path fill="#34A853"
                    d="M130.55 261.1c35.248 0 64.839-11.605 86.453-31.622l-41.196-31.913c-11.024 7.688-25.82 13.055-45.257 13.055-34.523 0-63.824-22.773-74.269-54.25l-1.531.13-40.298 31.187-.527 1.465C35.393 231.798 79.49 261.1 130.55 261.1">
                </path>
                <path fill="#FBBC05"
                    d="M56.281 156.37c-2.756-8.123-4.351-16.827-4.351-25.82 0-8.994 1.595-17.697 4.206-25.82l-.073-1.73L15.26 71.312l-1.335.635C5.077 89.644 0 109.517 0 130.55s5.077 40.905 13.925 58.602l42.356-32.782">
                </path>
                <path fill="#EB4335"
                    d="M130.55 50.479c24.514 0 41.05 10.589 50.479 19.438l36.844-35.974C195.245 12.91 165.798 0 130.55 0 79.49 0 35.393 29.301 13.925 71.947l42.211 32.783c10.59-31.477 39.891-54.251 74.414-54.251">
                </path>
            </svg>
            &nbsp;&nbsp;Login with Google
        </button>
        <button onclick="handleSignoutClick()" class="btn btn-danger" id="signout_button">Sign Out</button>
    </div>
    <div id="content"></div>
@endsection

@section('scripts')
    <script type="text/javascript">
        const CLIENT_ID = '{{ config('services.google.client_id') }}';
        const API_KEY = '{{ config('services.google.api_key') }}';

        const DISCOVERY_DOC = 'https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest';

        const SCOPES = 'https://www.googleapis.com/auth/calendar.readonly';

        let tokenClient;
        let gapiInited = false;
        let gisInited = false;

        document.getElementById('authorize_button').style.visibility = 'hidden';
        document.getElementById('signout_button').style.visibility = 'hidden';

        function gapiLoaded() {
            gapi.load('client', initializeGapiClient);
        }

        async function initializeGapiClient() {
            await gapi.client.init({
                apiKey: API_KEY,
                discoveryDocs: [DISCOVERY_DOC],
            });
            gapiInited = true;
            maybeEnableButtons();

            const storedToken = localStorage.getItem('google_token');
            if (storedToken !== null) {
                const token = JSON.parse(storedToken);
                gapi.client.setToken(token);
                document.getElementById('signout_button').style.visibility = 'visible';
                document.getElementById('authorize_button').style.visibility = 'hidden';
                await listUpcomingEvents();
            }
        }

        function gisLoaded() {
            tokenClient = google.accounts.oauth2.initTokenClient({
                client_id: CLIENT_ID,
                scope: SCOPES,
                callback: '',
            });
            gisInited = true;
            maybeEnableButtons();
        }

        function maybeEnableButtons() {
            if (gapiInited && gisInited) {
                document.getElementById('authorize_button').style.visibility = 'visible';
            }
        }

        function handleAuthClick() {
            tokenClient.callback = async (resp) => {
                if (resp.error !== undefined) {
                    throw (resp);
                }
                document.getElementById('signout_button').style.visibility = 'visible';
                document.getElementById('authorize_button').innerText = 'Refresh';
                await listUpcomingEvents();

                const token = gapi.client.getToken();
                localStorage.setItem('google_token', JSON.stringify(token));
            };

            if (gapi.client.getToken() === null) {
                tokenClient.requestAccessToken({
                    prompt: 'consent'
                });
            } else {
                tokenClient.requestAccessToken({
                    prompt: ''
                });
            }
        }

        function handleSignoutClick() {
            const token = gapi.client.getToken();
            if (token !== null) {
                google.accounts.oauth2.revoke(token.access_token);
                gapi.client.setToken('');
                localStorage.removeItem('google_token');
                document.getElementById('content').innerText = '';
                document.getElementById('authorize_button').innerText = 'Login with Google';
                document.getElementById('signout_button').style.visibility = 'hidden';
                document.getElementById('authorize_button').style.visibility = 'visible';
            }
        }

        async function listUpcomingEvents() {
            let response;
            try {
                const request = {
                    'calendarId': 'primary',
                    'timeMin': (new Date()).toISOString(),
                    'showDeleted': false,
                    'singleEvents': true,
                    'maxResults': 10,
                    'orderBy': 'startTime',
                };
                response = await gapi.client.calendar.events.list(request);
                console.log(response);
            } catch (err) {
                document.getElementById('content').innerText = err.message;
                return;
            }

            const events = response.result.items;
            if (!events || events.length === 0) {
                document.getElementById('content').innerText = 'No events found.';
                return;
            }

            const contentElement = document.getElementById('content');
            contentElement.innerHTML = ''; // Clear previous content

            const eventsContainer = document.createElement('div');
            eventsContainer.classList.add('container');

            const eventsList = document.createElement('ul');
            eventsList.classList.add('list-group');

            events.forEach(event => {
                const listItem = document.createElement('li');
                listItem.classList.add('list-group-item');

                const eventSummary = document.createElement('span');
                eventSummary.classList.add('fw-bold');
                eventSummary.textContent = event.summary;

                const eventDateTime = document.createElement('span');
                eventDateTime.textContent = event.start.dateTime || event.start.date;

                listItem.appendChild(eventSummary);
                listItem.appendChild(document.createElement('br'));
                listItem.appendChild(eventDateTime);
                eventsList.appendChild(listItem);
            });

            eventsContainer.appendChild(eventsList);
            contentElement.appendChild(eventsContainer);
        }
    </script>
    <script async defer src="https://apis.google.com/js/api.js" onload="gapiLoaded()"></script>
    <script async defer src="https://accounts.google.com/gsi/client" onload="gisLoaded()"></script>
@endsection
