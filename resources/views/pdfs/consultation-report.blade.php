<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; background: #f7f4ff; color: #1a1a1a; font-size: 13px; line-height: 1.75; }

  .header { background-color: #6d28d9; color: #ffffff; padding: 32px 32px 28px; }
  .header-top { font-size: 11px; letter-spacing: 1.2px; text-transform: uppercase; opacity: 0.75; margin-bottom: 10px; }
  .header-greeting { font-size: 24px; font-weight: bold; margin-bottom: 4px; }
  .header-sub { font-size: 13px; opacity: 0.85; margin-bottom: 18px; }
  .header-meta-table { width: 100%; border-collapse: collapse; }
  .header-meta-table td { font-size: 11px; color: rgba(255,255,255,0.75); padding: 0 16px 0 0; white-space: nowrap; }
  .header-meta-table td strong { display: block; font-size: 13px; color: #ffffff; font-weight: bold; }

  .page { padding: 26px 28px 40px; }
  .section { margin-bottom: 28px; }
  .section-title { font-size: 15px; font-weight: bold; color: #3b0764; margin-bottom: 14px; }

  .info-table { width: 100%; border-collapse: separate; border-spacing: 8px; }
  .info-card { background: #ffffff; border-radius: 12px; padding: 14px 10px; text-align: center; border: 1px solid #ede9fe; }
  .info-card .i-value { font-size: 13px; font-weight: bold; color: #3b0764; }
  .info-card .i-label { font-size: 10px; color: #888; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.4px; }

  .badge { display: inline-block; background: #ede9fe; color: #5b21b6; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; margin: 3px 5px 3px 0; border: 1px solid #ddd6fe; }

  .condition-summary { background: #f5f0ff; border-radius: 12px; border: 1px solid #ddd6fe; padding: 16px 18px; font-size: 13px; color: #3b0764; line-height: 1.8; }

  .finding-card { background: #ffffff; border-radius: 12px; border: 1px solid #ede9fe; margin-bottom: 10px; overflow: hidden; }
  .finding-card-header { background: #f5f0ff; padding: 10px 16px; border-bottom: 1px solid #ede9fe; display: flex; align-items: center; gap: 8px; }
  .finding-area-badge { display: inline-block; background: #6d28d9; color: #fff; font-size: 10px; font-weight: bold; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.6px; }
  .severity-low { display: inline-block; background: #f0fdf4; color: #166534; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 20px; border: 1px solid #bbf7d0; }
  .severity-medium { display: inline-block; background: #fefce8; color: #854d0e; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 20px; border: 1px solid #fde68a; }
  .severity-high { display: inline-block; background: #fff1f2; color: #9f1239; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 20px; border: 1px solid #fecdd3; }
  .finding-card-body { padding: 12px 16px; }
  .finding-obs { font-size: 13px; color: #555; line-height: 1.75; }

  .risk-card { background: #fff7ed; border-radius: 10px; border: 1px solid #fed7aa; padding: 12px 14px; margin-bottom: 8px; }
  .risk-title { font-size: 13px; font-weight: bold; color: #c2410c; }
  .risk-reason { font-size: 12px; color: #78350f; margin-top: 3px; line-height: 1.6; }

  .goal-card { background: #ffffff; border-radius: 12px; border: 1px solid #ede9fe; padding: 12px 16px; margin-bottom: 8px; }
  .goal-name { font-size: 13px; font-weight: bold; color: #3b0764; }
  .goal-status { font-size: 12px; color: #6b7280; margin-top: 3px; }
  .priority-high { display: inline-block; background: #fff1f2; color: #9f1239; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 20px; border: 1px solid #fecdd3; margin-left: 6px; }
  .priority-medium { display: inline-block; background: #fefce8; color: #854d0e; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 20px; border: 1px solid #fde68a; margin-left: 6px; }
  .priority-low { display: inline-block; background: #f0fdf4; color: #166534; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 20px; border: 1px solid #bbf7d0; margin-left: 6px; }

  .focus-card { background: #ffffff; border-radius: 12px; border: 1px solid #d1fae5; padding: 12px 16px; margin-bottom: 8px; }
  .focus-area { font-size: 13px; font-weight: bold; color: #065f46; }
  .focus-why { font-size: 12px; color: #6b7280; margin-top: 3px; }
  .focus-action { font-size: 12px; color: #047857; font-weight: bold; margin-top: 4px; }

  .rec-card { background: #ffffff; border-radius: 12px; border: 1px solid #d1fae5; padding: 14px 16px; margin-bottom: 10px; }
  .rec-label { font-size: 11px; font-weight: bold; color: #065f46; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 5px; }
  .rec-text { font-size: 13px; color: #4b4b4b; line-height: 1.75; }

  .step-card { background: #ffffff; border-radius: 12px; border: 1px solid #fde68a; padding: 14px 16px; margin-bottom: 10px; }
  .step-label { font-size: 11px; font-weight: bold; color: #b45309; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 5px; }
  .step-text { font-size: 13px; color: #4b4b4b; line-height: 1.75; }

  .coach-note { background: #faf5ff; border-radius: 12px; border: 1px solid #ddd6fe; padding: 18px 20px; font-size: 14px; color: #3b0764; line-height: 1.9; font-style: italic; }
  .coach-note-label { font-size: 11px; font-weight: bold; color: #7c3aed; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 8px; font-style: normal; }

  .precaution-card { background: #eff6ff; border-radius: 10px; border: 1px solid #bfdbfe; padding: 10px 14px; margin-bottom: 8px; }
  .precaution-text { font-size: 12px; color: #1e40af; line-height: 1.6; }

  .divider { border: none; border-top: 1px solid #e9e3ff; margin: 22px 0; }

  .footer { margin-top: 36px; text-align: center; color: #aaa; font-size: 11px; border-top: 1px solid #e9e3ff; padding-top: 16px; line-height: 1.9; }
  .footer-brand { font-size: 12px; font-weight: bold; color: #6d28d9; margin-top: 6px; }
</style>
</head>
<body>

  <div class="header">
    <div class="header-top">&#127807; Rakhi Health Coach &nbsp;&bull;&nbsp; Consultation Report</div>
    <div class="header-greeting">Your Health Report, {{ $user->first_name }}! &#128203;</div>
    <div class="header-sub">A full summary of your health consultation with Rakhi.</div>
    <table class="header-meta-table">
      <tr>
        @if($user->getAge())<td><strong>{{ $user->getAge() }} yrs</strong>Age</td>@endif
        @if($user->gender)<td><strong>{{ ucfirst($user->gender) }}</strong>Gender</td>@endif
        @if($user->weight)<td><strong>{{ $user->weight }} kg</strong>Weight</td>@endif
        @if($user->height)<td><strong>{{ $user->height }} cm</strong>Height</td>@endif
        <td><strong>{{ $date }}</strong>Generated</td>
      </tr>
    </table>
  </div>

  <div class="page">

    <!-- Profile -->
    <div class="section">
      <div class="section-title">&#128100; Your Profile</div>
      <table class="info-table">
        <tr>
          @php
            $diet     = $user->diet_preference ?? ($memory['diet_habit'] ?? ($memory['food_preference'] ?? null));
            $stress   = $user->stress_level    ?? ($memory['stress_level'] ?? null);
            $sleep    = $user->sleep_hours     ?? ($memory['sleep_pattern'] ?? null);
            $activity = $user->activity_level  ?? ($memory['activity_level'] ?? null);
            $stage    = $memory['current_stage'] ?? null;
          @endphp
          @if($diet)<td class="info-card"><div class="i-value">{{ ucfirst($diet) }}</div><div class="i-label">Diet</div></td>@endif
          @if($activity)<td class="info-card"><div class="i-value">{{ ucfirst($activity) }}</div><div class="i-label">Activity</div></td>@endif
          @if($sleep)<td class="info-card"><div class="i-value">{{ $sleep }} hrs</div><div class="i-label">Sleep / Night</div></td>@endif
          @if($stress)<td class="info-card"><div class="i-value">{{ ucfirst($stress) }}</div><div class="i-label">Stress Level</div></td>@endif
          @if($stage)<td class="info-card"><div class="i-value">{{ ucfirst($stage) }}</div><div class="i-label">Current Stage</div></td>@endif
        </tr>
      </table>
    </div>

    <hr class="divider">

    <!-- Goals -->
    <div class="section">
      <div class="section-title">&#127919; Health Goals</div>
      @foreach($user->goals as $goal)
        <span class="badge">{{ $goal->name }}</span>
      @endforeach
    </div>

    <hr class="divider">

    <!-- Condition Summary -->
    @if(!empty($report['condition_summary']))
    <div class="section">
      <div class="section-title">&#128203; Condition Summary</div>
      <div class="condition-summary">{{ $report['condition_summary'] }}</div>
    </div>
    <hr class="divider">
    @endif

    <!-- Key Observations -->
    @if(!empty($report['key_observations']))
    <div class="section">
      <div class="section-title">&#128269; Key Observations</div>
      @foreach($report['key_observations'] as $finding)
      <div class="finding-card">
        <div class="finding-card-header">
          <span class="finding-area-badge">{{ $finding['area'] ?? '' }}</span>
          @if(!empty($finding['severity']))
            <span class="severity-{{ $finding['severity'] }}">{{ ucfirst($finding['severity']) }}</span>
          @endif
        </div>
        <div class="finding-card-body">
          <div class="finding-obs">{{ $finding['observation'] ?? '' }}</div>
        </div>
      </div>
      @endforeach
    </div>
    <hr class="divider">
    @endif

    <!-- Identified Risks -->
    @if(!empty($report['identified_risks']))
    <div class="section">
      <div class="section-title">&#9888; Identified Risks</div>
      @foreach($report['identified_risks'] as $risk)
      <div class="risk-card">
        <div class="risk-title">{{ $risk['risk'] ?? '' }}</div>
        @if(!empty($risk['reason']))<div class="risk-reason">{{ $risk['reason'] }}</div>@endif
      </div>
      @endforeach
    </div>
    <hr class="divider">
    @endif

    <!-- Goals with Status -->
    @if(!empty($report['goals']))
    <div class="section">
      <div class="section-title">&#127919; Goal Status</div>
      @foreach($report['goals'] as $goal)
      <div class="goal-card">
        <div class="goal-name">
          {{ $goal['goal'] ?? '' }}
          @if(!empty($goal['priority']))
            <span class="priority-{{ $goal['priority'] }}">{{ ucfirst($goal['priority']) }} priority</span>
          @endif
        </div>
        @if(!empty($goal['current_status']))<div class="goal-status">Current: {{ $goal['current_status'] }}</div>@endif
      </div>
      @endforeach
    </div>
    <hr class="divider">
    @endif

    <!-- Focus Areas -->
    @if(!empty($report['focus_areas']))
    <div class="section">
      <div class="section-title">&#128640; Focus Areas</div>
      @foreach($report['focus_areas'] as $area)
      <div class="focus-card">
        <div class="focus-area">&#9679; {{ $area['area'] ?? '' }}</div>
        @if(!empty($area['why']))<div class="focus-why">{{ $area['why'] }}</div>@endif
        @if(!empty($area['action']))<div class="focus-action">&#8594; {{ $area['action'] }}</div>@endif
      </div>
      @endforeach
    </div>
    <hr class="divider">
    @endif

    <!-- Recommendations (legacy support) -->
    @if(!empty($report['recommendations']))
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

    <!-- Next Steps (legacy support) -->
    @if(!empty($report['next_steps']))
    <div class="section">
      <div class="section-title">&#128161; Next Steps</div>
      @foreach($report['next_steps'] as $step)
      <div class="step-card">
        <div class="step-label">&#128161; Action:</div>
        <div class="step-text">{{ $step }}</div>
      </div>
      @endforeach
    </div>
    <hr class="divider">
    @endif

    <!-- Coach Note -->
    @if(!empty($report['coach_note']))
    <div class="section">
      <div class="coach-note">
        <div class="coach-note-label">&#128140; A note from Rakhi</div>
        {{ $report['coach_note'] }}
      </div>
    </div>
    <hr class="divider">
    @endif

    <!-- Precautions -->
    @if(!empty($report['precautions']))
    <div class="section">
      <div class="section-title">&#9888; Precautions</div>
      @foreach($report['precautions'] as $p)
      <div class="precaution-card">
        <div class="precaution-text">&#8226; {{ $p }}</div>
      </div>
      @endforeach
    </div>
    @endif

    <div class="footer">
      <p>
        This report is generated by Rakhi Health Coach for lifestyle and wellness guidance only.<br>
        It does not constitute medical advice. Please consult a qualified healthcare professional for medical decisions.
      </p>
      <div class="footer-brand">&#127807; Rakhi Health Coach &nbsp;&bull;&nbsp; {{ $date }}</div>
    </div>

  </div>
</body>
</html>
