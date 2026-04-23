<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'DejaVu Sans', sans-serif;
    background: #f7f4ff;
    color: #1a1a1a;
    font-size: 13px;
    line-height: 1.75;
  }

  /* ── Header ── */
  .header {
    background-color: #6d28d9;
    color: #ffffff;
    padding: 32px 32px 28px;
  }
  .header-top {
    font-size: 11px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    opacity: 0.75;
    margin-bottom: 10px;
  }
  .header-greeting {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 4px;
    letter-spacing: 0.2px;
  }
  .header-sub {
    font-size: 13px;
    opacity: 0.85;
    margin-bottom: 18px;
  }
  .header-meta-table { width: 100%; border-collapse: collapse; }
  .header-meta-table td {
    font-size: 11px;
    color: rgba(255,255,255,0.75);
    padding: 0 16px 0 0;
    white-space: nowrap;
  }
  .header-meta-table td strong {
    display: block;
    font-size: 13px;
    color: #ffffff;
    font-weight: bold;
  }

  /* ── Page wrapper ── */
  .page { padding: 26px 28px 40px; }

  /* ── Section ── */
  .section { margin-bottom: 28px; }

  .section-title {
    font-size: 15px;
    font-weight: bold;
    color: #3b0764;
    margin-bottom: 14px;
  }

  /* ── Profile stat cards ── */
  .info-table { width: 100%; border-collapse: separate; border-spacing: 8px; }
  .info-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 14px 10px;
    text-align: center;
    border: 1px solid #ede9fe;
    width: 33%;
  }
  .info-card .i-icon { font-size: 18px; margin-bottom: 5px; }
  .info-card .i-value { font-size: 14px; font-weight: bold; color: #3b0764; }
  .info-card .i-label { font-size: 11px; color: #888; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.4px; }

  /* ── Goals badges ── */
  .badge {
    display: inline-block;
    background: #ede9fe;
    color: #5b21b6;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin: 3px 5px 3px 0;
    border: 1px solid #ddd6fe;
  }

  /* ── Finding card ── */
  .finding-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #ede9fe;
    margin-bottom: 10px;
    overflow: hidden;
  }
  .finding-card-header {
    background: #f5f0ff;
    padding: 10px 16px;
    border-bottom: 1px solid #ede9fe;
  }
  .finding-area-badge {
    display: inline-block;
    background: #6d28d9;
    color: #fff;
    font-size: 10px;
    font-weight: bold;
    padding: 3px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
  }
  .finding-card-body { padding: 12px 16px; }
  .finding-obs {
    font-size: 13px;
    color: #555;
    line-height: 1.75;
  }

  /* ── Recommendation card ── */
  .rec-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #d1fae5;
    padding: 14px 16px;
    margin-bottom: 10px;
  }
  .rec-label {
    font-size: 11px;
    font-weight: bold;
    color: #065f46;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 5px;
  }
  .rec-text {
    font-size: 13px;
    color: #4b4b4b;
    line-height: 1.75;
  }

  /* ── Next step card ── */
  .step-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #fde68a;
    padding: 14px 16px;
    margin-bottom: 10px;
  }
  .step-label {
    font-size: 11px;
    font-weight: bold;
    color: #b45309;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 5px;
  }
  .step-text {
    font-size: 13px;
    color: #4b4b4b;
    line-height: 1.75;
  }

  /* ── Divider ── */
  .divider {
    border: none;
    border-top: 1px solid #e9e3ff;
    margin: 24px 0;
  }

  /* ── Footer ── */
  .footer {
    margin-top: 36px;
    text-align: center;
    color: #aaa;
    font-size: 11px;
    border-top: 1px solid #e9e3ff;
    padding-top: 16px;
    line-height: 1.9;
  }
  .footer-brand {
    font-size: 12px;
    font-weight: bold;
    color: #6d28d9;
    margin-top: 6px;
  }
</style>
</head>
<body>

  <!-- ── Header ── -->
  <div class="header">
    <div class="header-top">&#127807; Rakhi AI &nbsp;&bull;&nbsp; Health Coach</div>
    <div class="header-greeting">Your Health Report, {{ $user->first_name }}! &#128203;</div>
    <div class="header-sub">Here's a full summary of your health consultation with Rakhi AI.</div>
    <table class="header-meta-table">
      <tr>
        @if($user->age())<td><strong>{{ $user->age() }} yrs</strong>Age</td>@endif
        @if($user->gender)<td><strong>{{ ucfirst($user->gender) }}</strong>Gender</td>@endif
        @if($user->weight)<td><strong>{{ $user->weight }} kg</strong>Weight</td>@endif
        @if($user->height)<td><strong>{{ $user->height }} cm</strong>Height</td>@endif
        <td><strong>{{ $date }}</strong>Generated</td>
      </tr>
    </table>
  </div>

  <div class="page">

    <!-- ── Profile ── -->
    <div class="section">
      <div class="section-title">&#128100; Your Profile</div>
      <table class="info-table">
        <tr>
          @php
            $diet     = $user->diet_preference ?? ($memory['diet_habit'] ?? ($memory['food_preference'] ?? null));
            $stress   = $user->stress_level    ?? ($memory['stress_level'] ?? null);
            $sleep    = $user->sleep_hours     ?? ($memory['sleep_pattern'] ?? null);
            $activity = $user->activity_level  ?? ($memory['activity_level'] ?? null);
          @endphp
          @if($diet)
          <td class="info-card">
            <div class="i-icon">&#127829;</div>
            <div class="i-value">{{ ucfirst($diet) }}</div>
            <div class="i-label">Diet</div>
          </td>
          @endif
          @if($stress)
          <td class="info-card">
            <div class="i-icon">&#128166;</div>
            <div class="i-value">{{ ucfirst($stress) }}</div>
            <div class="i-label">Stress Level</div>
          </td>
          @endif
          @if($sleep)
          <td class="info-card">
            <div class="i-icon">&#128564;</div>
            <div class="i-value">{{ $sleep }} hrs</div>
            <div class="i-label">Sleep / Night</div>
          </td>
          @endif
        </tr>
        <tr>
          @if($activity)
          <td class="info-card">
            <div class="i-icon">&#127939;</div>
            <div class="i-value">{{ ucfirst($activity) }}</div>
            <div class="i-label">Activity Level</div>
          </td>
          @endif
          @if($user->weight)
          <td class="info-card">
            <div class="i-icon">&#9878;</div>
            <div class="i-value">{{ $user->weight }} kg</div>
            <div class="i-label">Weight</div>
          </td>
          @endif
          @if($user->height)
          <td class="info-card">
            <div class="i-icon">&#128200;</div>
            <div class="i-value">{{ $user->height }} cm</div>
            <div class="i-label">Height</div>
          </td>
          @endif
        </tr>
      </table>
    </div>

    <hr class="divider">

    <!-- ── Goals ── -->
    <div class="section">
      <div class="section-title">&#127919; Health Goals</div>
      @foreach($user->goals as $goal)
        <span class="badge">{{ $goal->name }}</span>
      @endforeach
    </div>

    <hr class="divider">

    <!-- ── Key Findings ── -->
    @if(isset($report['findings']))
    <div class="section">
      <div class="section-title">&#128269; Key Findings</div>
      @foreach($report['findings'] as $finding)
      <div class="finding-card">
        <div class="finding-card-header">
          <span class="finding-area-badge">{{ $finding['area'] ?? '' }}</span>
        </div>
        <div class="finding-card-body">
          <div class="finding-obs">{{ $finding['observation'] ?? '' }}</div>
        </div>
      </div>
      @endforeach
    </div>
    <hr class="divider">
    @endif

    <!-- ── Recommendations ── -->
    @if(isset($report['recommendations']))
    <div class="section">
      <div class="section-title">&#9989; Recommendations</div>
      @foreach($report['recommendations'] as $rec)
      <div class="rec-card">
        <div class="rec-label">&#128172; Rakhi recommends:</div>
        <div class="rec-text">{{ $rec }}</div>
      </div>
      @endforeach
    </div>
    <hr class="divider">
    @endif

    <!-- ── Next Steps ── -->
    @if(isset($report['next_steps']))
    <div class="section">
      <div class="section-title">&#128640; Next Steps</div>
      @foreach($report['next_steps'] as $step)
      <div class="step-card">
        <div class="step-label">&#128161; Action:</div>
        <div class="step-text">{{ $step }}</div>
      </div>
      @endforeach
    </div>
    @endif

    <!-- ── Footer ── -->
    <div class="footer">
      <p>
        This report is generated by Rakhi AI for lifestyle and wellness guidance only.<br>
        It does not constitute medical advice. Please consult a qualified healthcare professional for medical decisions.
      </p>
      <div class="footer-brand">&#127807; Rakhi AI &nbsp;&bull;&nbsp; rakhi.ai &nbsp;&bull;&nbsp; {{ $date }}</div>
    </div>

  </div>
</body>
</html>
