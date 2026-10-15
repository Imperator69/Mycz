<div class="overflow-x-auto">
    <p class="mycz-kpi-hint" style="margin-bottom: .5rem">Monatsrate {{ eur($payment) }}. Bei variablem Zins wird der aktuelle Zinssatz fortgeschrieben.</p>
    <table class="mycz-table">
        <thead>
            <tr><th>Jahr</th><th class="num">Zahlungen</th><th class="num">Zinsen</th><th class="num">Tilgung</th><th class="num">Restschuld Jahresende</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr @class(['font-semibold' => $row['year'] === (int) date('Y')])>
                    <td>{{ $row['year'] }}</td>
                    <td class="num">{{ eur($row['payment']) }}</td>
                    <td class="num">{{ eur($row['interest']) }}</td>
                    <td class="num">{{ eur($row['principal']) }}</td>
                    <td class="num">{{ eur($row['balance']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
