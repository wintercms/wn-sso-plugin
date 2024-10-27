# Google SSO Setup Instructions

<div class="callout callout-info no-subheader">
    <div class="header">
        <i class="icon-info"></i>
        <h3>How to configure</h3>
    </div>
    <div class="content">
        <ol>
            <li>
                In the Google Cloud console, go to Menu &gt; <strong>IAM &amp; Admin</strong> &gt; <strong>Create a Project</strong>.<br>
                <a target="_blank" href="https://console.cloud.google.com/projectcreate">https://console.cloud.google.com/projectcreate</a>
            </li>
            <li>In the <strong>Project Name</strong> field, enter a descriptive name for your project. (ex: Winter OAuth)</li>
            <li>
                Click <strong>Create</strong>. Once the project is created, go to <strong>APIs & Services</strong> &gt; <strong>Credentials</strong>.<br>
                <a target="_blank" href="https://console.cloud.google.com/apis/credentials">https://console.cloud.google.com/apis/credentials</a>
            </li>
            <li>Click <strong>Create credentials</strong>, then select <strong>OAuth client ID</strong> from the menu.</li>
            <li>If prompted, click <strong>Configure consent screen</strong>. Once configured, go back to <strong>Credentials</strong>.</li>
            <li>
                For the <strong>Application type</strong>, select <strong>Web application</strong>.
                <ul>
                    <li><strong>Authorized JavaScript origins:</strong> Leave this field blank.</li>
                    <li><strong>Authorized redirect URIs:</strong> <code>http://localhost/backend/winter/sso/handle/callback/google</code> (replace <code>http://localhost</code> with your domain name)</li>
                </ul>
            </li>
            <li>Click <strong>Create</strong>. Copy the <strong>Client ID</strong> and <strong>Client secret</strong>.</li>
            <li>
                Add the configuration using one of the following ways:
                <ul>
                    <li>Using the <code>config/services.php</code> file. See the <a target="_blank" href="https://socialiteproviders.com/Google-Plus/#add-configuration-to-config-services-php">Socialite Providers documentation</a> for more information.</li>
                    <li>Using the environment variables: <code>GOOGLE_CLIENT_ID</code>, <code>GOOGLE_CLIENT_SECRET</code>, and <code>GOOGLE_REDIRECT_URI</code>.</li>
                </ul>
            </li>
        </ol>
    </div>
</div>
