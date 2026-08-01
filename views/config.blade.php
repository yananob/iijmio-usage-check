<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IIJmio Usage Checker Config</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 1em; line-height: 1.6; background-color: #f9f9f9; }
        .container { max-width: 800px; margin: auto; background: white; padding: 1.5em; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .footer { margin-top: 2em; font-size: 0.8em; color: #666; text-align: center; }
        .message { color: #2e7d32; font-weight: bold; background: #e8f5e9; padding: 1em; border-radius: 4px; margin-bottom: 1em; }
        section { margin-bottom: 2em; border: 1px solid #eee; padding: 1em; border-radius: 4px; }
        h1 { text-align: center; color: #333; font-size: 1.5em; }
        h2 { margin-top: 0; color: #555; border-bottom: 2px solid #eee; padding-bottom: 0.5em; font-size: 1.2em; }
        h3 { color: #666; font-size: 1.1em; }
        .field { margin-bottom: 15px; }
        label { display: inline-block; width: 100%; max-width: 200px; font-weight: bold; vertical-align: top; margin-bottom: 5px; }
        input[type="text"], input[type="password"], input[type="number"] { width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .table-container { overflow-x: auto; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        button { padding: 10px 20px; cursor: pointer; border-radius: 4px; border: none; transition: background 0.2s; }
        .btn-add { background-color: #0288d1; color: white; margin-bottom: 1em; }
        .btn-add:hover { background-color: #0277bd; }
        .btn-save { background-color: #43a047; color: white; font-size: 1.1em; display: block; width: 100%; }
        .btn-save:hover { background-color: #388e3c; }
        .btn-remove { background-color: #e53935; color: white; padding: 6px 12px; }
        .btn-remove:hover { background-color: #d32f2f; }
        .env-info { background: #eee; padding: 0.2em 0.5em; border-radius: 4px; font-family: monospace; display: inline-block; word-break: break-all; }

        @media (max-width: 600px) {
            body { padding: 0.5em; }
            .container { padding: 1em; }
            label { display: block; max-width: none; }
            input[type="text"], input[type="password"], input[type="number"] { max-width: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>IIJmio Usage Checker Config</h1>
        @if($message)
            <div class="message">{{ $message }}</div>
        @endif

        <p>Firestore Collection: <span class="env-info">{{ $collectionName }}</span></p>

        <form method="POST">
            <section>
                <h2>IIJmio Settings</h2>
                <div class="field">
                    <label for="mio_id">Mio ID:</label>
                    <input type="text" id="mio_id" name="iijmio[mio_id]" value="{{ $config['iijmio']['mio_id'] ?? '' }}" required placeholder="MA1234567">
                </div>
                <div class="field">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="iijmio[password]" value="{{ $config['iijmio']['password'] ?? '' }}" required>
                </div>

                <h3>Users</h3>
                <div class="table-container">
                    <table id="users-table">
                        <thead>
                            <tr>
                                <th>HDO Code (e.g. hdo12345678)</th>
                                <th>Name</th>
                                <th>Plan Data Volume (GB)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="users-body">
                        @php $userIndex = 0; @endphp
                        @if(isset($config['iijmio']['users']) && is_array($config['iijmio']['users']))
                            @foreach($config['iijmio']['users'] as $code => $user)
                            @php
                                $name = is_array($user) ? ($user['name'] ?? '') : (is_object($user) ? ($user->name ?? '') : $user);
                                $vol = is_array($user) ? ($user['plan_data_volume'] ?? '') : (is_object($user) ? ($user->plan_data_volume ?? '') : '');
                            @endphp
                            <tr>
                                <td><input type="text" name="iijmio[users][{{ $userIndex }}][code]" value="{{ $code }}" required></td>
                                <td><input type="text" name="iijmio[users][{{ $userIndex }}][name]" value="{{ $name }}" required></td>
                                <td><input type="number" step="0.1" name="iijmio[users][{{ $userIndex }}][plan_data_volume]" value="{{ $vol }}" required></td>
                                <td><button type="button" class="btn-remove" onclick="removeRow(this)">Remove</button></td>
                            </tr>
                            @php $userIndex++; @endphp
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn-add" id="add-user">+ Add User</button>
            </section>

            <section>
                <h2>Alert Settings</h2>
                <div class="field">
                    <label for="alert_bot">Bot Name:</label>
                    <input type="text" id="alert_bot" name="alert[bot]" value="{{ $config['alert']['bot'] ?? '' }}" required>
                </div>
                <div class="field">
                    <label for="alert_target">Target Name:</label>
                    <input type="text" id="alert_target" name="alert[target]" value="{{ $config['alert']['target'] ?? '' }}" required>
                </div>
                <div class="field">
                    <label for="alert_days">Send usage each N days:</label>
                    <input type="number" id="alert_days" name="alert[send_usage_each_n_days]" value="{{ $config['alert']['send_usage_each_n_days'] ?? '' }}" required>
                </div>
            </section>

            <button type="submit" class="btn-save">Save Config</button>
        </form>

        <div class="footer">
            Environment: <span class="env-info">{{ $appEnv }}</span>
        </div>
    </div>

    <script>
        let userIndex = {{ $userIndex }};
        document.getElementById('add-user').addEventListener('click', function() {
            const tbody = document.getElementById('users-body');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="iijmio[users][${userIndex}][code]" value="" required placeholder="hdo12345678"></td>
                <td><input type="text" name="iijmio[users][${userIndex}][name]" value="" required placeholder="Name"></td>
                <td><input type="number" step="0.1" name="iijmio[users][${userIndex}][plan_data_volume]" value="" required placeholder="4.0"></td>
                <td><button type="button" class="btn-remove" onclick="removeRow(this)">Remove</button></td>
            `;
            tbody.appendChild(tr);
            userIndex++;
        });

        function removeRow(btn) {
            btn.parentElement.parentElement.remove();
        }
    </script>
</body>
</html>
