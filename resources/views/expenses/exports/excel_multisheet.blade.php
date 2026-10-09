{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
{!! '<' . '?mso-application progid="Excel.Sheet"?' . '>' !!}
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>Bank Complaint Manager</Author>
  <Company>Bank Complaint Support Services</Company>
  <Created>{{ now()->toIso8601String() }}</Created>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#1E293B"/>
  </Style>
  <!-- Header Styles -->
  <Style ss:ID="HeaderMaster">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#0F172A" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#334155"/>
   </Borders>
  </Style>
  <Style ss:ID="HeaderSummary">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#047857" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#065F46"/>
   </Borders>
  </Style>
  <!-- Data Styles -->
  <Style ss:ID="CellLeft">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="CellCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="CellNumber">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
  <Style ss:ID="CellCurrency">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <NumberFormat ss:Format="PKR #,##0.00"/>
  </Style>
  <Style ss:ID="CellInteger">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <NumberFormat ss:Format="#,##0"/>
  </Style>
  <!-- Status Badges -->
  <Style ss:ID="StatusPaid">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#065F46"/>
   <Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="StatusApproved">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1E40AF"/>
   <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="StatusPending">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#92400E"/>
   <Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="StatusRejected">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#991B1B"/>
   <Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>
  </Style>
  <!-- Totals Row -->
  <Style ss:ID="TotalLabel">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0F172A"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
    <Border ss:Position="Bottom" ss:LineStyle="Double" ss:Weight="2" ss:Color="#475569"/>
   </Borders>
  </Style>
  <Style ss:ID="TotalInteger">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0F172A"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0"/>
   <Borders>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
    <Border ss:Position="Bottom" ss:LineStyle="Double" ss:Weight="2" ss:Color="#475569"/>
   </Borders>
  </Style>
  <Style ss:ID="TotalNumber">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0F172A"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0.00"/>
   <Borders>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
    <Border ss:Position="Bottom" ss:LineStyle="Double" ss:Weight="2" ss:Color="#475569"/>
   </Borders>
  </Style>
  <Style ss:ID="TotalCurrency">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0F172A"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="PKR #,##0.00"/>
   <Borders>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
    <Border ss:Position="Bottom" ss:LineStyle="Double" ss:Weight="2" ss:Color="#475569"/>
   </Borders>
  </Style>
 </Styles>

 <!-- SHEET 1: MASTER SHEET -->
 <Worksheet ss:Name="Master Sheet">
  <Table>
   <Column ss:Width="65"/>  <!-- Claim ID -->
   <Column ss:Width="85"/>  <!-- Ticket No -->
   <Column ss:Width="160"/> <!-- Bank Name -->
   <Column ss:Width="140"/> <!-- Branch / Location -->
   <Column ss:Width="220"/> <!-- Branch Address -->
   <Column ss:Width="130"/> <!-- Engineer Name -->
   <Column ss:Width="140"/> <!-- Engineer Location -->
   <Column ss:Width="90"/>  <!-- Category -->
   <Column ss:Width="180"/> <!-- Description -->
   <Column ss:Width="85"/>  <!-- From City -->
   <Column ss:Width="85"/>  <!-- To City -->
   <Column ss:Width="95"/>  <!-- Trip Type -->
   <Column ss:Width="110"/> <!-- AI Distance KM -->
   <Column ss:Width="130"/> <!-- Claimed Amount PKR -->
   <Column ss:Width="130"/> <!-- Applied Rate PKR/KM -->
   <Column ss:Width="95"/>  <!-- Claim Status -->
   <Column ss:Width="120"/> <!-- Settlement Status -->
   <Column ss:Width="110"/> <!-- Payment Method -->
   <Column ss:Width="120"/> <!-- Payment Ref -->
   <Column ss:Width="115"/> <!-- Paid At -->
   <Column ss:Width="115"/> <!-- Submission Date -->

   <Row ss:Height="26">
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Claim ID</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Ticket No</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Bank Name</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Branch / Location</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Branch Address</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Engineer Name</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Engineer Location</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Category</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Description</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">From City</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">To City</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Trip Type</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">AI Distance (KM)</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Claimed Amount (PKR)</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Applied Rate (PKR/KM)</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Claim Status</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Settlement Status</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Payment Method</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Payment Ref</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Paid At</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Submission Date</Data></Cell>
   </Row>

   @foreach($claims as $c)
   @php
       $statusStyle = 'StatusPending';
       if ($c->status === 'paid') $statusStyle = 'StatusPaid';
       elseif ($c->status === 'approved') $statusStyle = 'StatusApproved';
       elseif ($c->status === 'rejected') $statusStyle = 'StatusRejected';
   @endphp
   <Row ss:Height="20">
    <Cell ss:StyleID="CellCenter"><Data ss:Type="Number">{{ $c->id }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ $c->ticket?->ticket_no ?? 'N/A' }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $c->ticket?->bank_name ?? 'N/A' }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $c->ticket?->branch_location ?? $c->to_city }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $c->ticket?->branch_address ?? 'N/A' }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $c->engineer?->name ?? 'N/A' }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $c->engineer?->base_city ?? ($c->engineer?->home_address ?? 'N/A') }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ strtoupper($c->category ?? 'TRAVEL') }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $c->description ?? 'N/A' }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $c->from_city }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $c->to_city }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ ucwords(str_replace('_', ' ', $c->trip_type)) }}</Data></Cell>
    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">{{ number_format($c->ai_distance_km ?? 0, 2, '.', '') }}</Data></Cell>
    <Cell ss:StyleID="CellCurrency"><Data ss:Type="Number">{{ number_format($c->claimed_amount, 2, '.', '') }}</Data></Cell>
    @if($c->applied_rate !== null)
     <Cell ss:StyleID="CellCurrency"><Data ss:Type="Number">{{ number_format($c->applied_rate, 2, '.', '') }}</Data></Cell>
    @else
     <Cell ss:StyleID="CellCenter"><Data ss:Type="String">N/A</Data></Cell>
    @endif
    <Cell ss:StyleID="{{ $statusStyle }}"><Data ss:Type="String">{{ strtoupper($c->status) }}</Data></Cell>
    <Cell ss:StyleID="{{ $c->status === 'paid' ? 'StatusPaid' : 'StatusPending' }}"><Data ss:Type="String">{{ $c->status === 'paid' ? 'Paid & Settled' : 'Unpaid & Unsettled' }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ strtoupper(str_replace('_', ' ', $c->payment_method ?? 'N/A')) }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ $c->payment_reference ?? 'N/A' }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ $c->paid_at ? $c->paid_at->format('Y-m-d H:i') : 'N/A' }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ $c->created_at->format('Y-m-d H:i') }}</Data></Cell>
   </Row>
   @endforeach

  </Table>
 </Worksheet>

 <!-- SHEET 2: ENGINEER SUMMARY -->
 <Worksheet ss:Name="Engineer Summary">
  <Table>
   <Column ss:Width="160"/> <!-- Engineer Name -->
   <Column ss:Width="100"/> <!-- Exp No's -->
   <Column ss:Width="180"/> <!-- Total AI Estimated Distance (KM) -->
   <Column ss:Width="160"/> <!-- Average Tour Cost (PKR) -->
   <Column ss:Width="160"/> <!-- Total Tour Cost (PKR) -->
   <Column ss:Width="160"/> <!-- Average Per KM Cost (PKR) -->

   <Row ss:Height="26">
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Engineer Name</Data></Cell>
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Exp No's</Data></Cell>
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Total AI Estimated Distance (KM)</Data></Cell>
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Average Tour Cost (PKR)</Data></Cell>
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Total Tour Cost (PKR)</Data></Cell>
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Average Per KM Cost (PKR)</Data></Cell>
   </Row>

   @foreach($engineerSummaries as $s)
   <Row ss:Height="20">
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $s['engineer_name'] }}</Data></Cell>
    <Cell ss:StyleID="CellInteger"><Data ss:Type="Number">{{ $s['exp_count'] }}</Data></Cell>
    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number">{{ number_format($s['total_ai_distance'], 2, '.', '') }}</Data></Cell>
    <Cell ss:StyleID="CellCurrency"><Data ss:Type="Number">{{ number_format($s['avg_tour_cost'], 2, '.', '') }}</Data></Cell>
    <Cell ss:StyleID="CellCurrency"><Data ss:Type="Number">{{ number_format($s['total_tour_cost'], 2, '.', '') }}</Data></Cell>
    <Cell ss:StyleID="CellCurrency"><Data ss:Type="Number">{{ number_format($s['avg_per_km_cost'] ?? 0, 2, '.', '') }}</Data></Cell>
   </Row>
   @endforeach

   <!-- Total / Overall Row -->
   <Row ss:Height="24">
    <Cell ss:StyleID="TotalLabel"><Data ss:Type="String">TOTAL / ALL ENGINEERS</Data></Cell>
    <Cell ss:StyleID="TotalInteger"><Data ss:Type="Number">{{ $totalCount }}</Data></Cell>
    <Cell ss:StyleID="TotalNumber"><Data ss:Type="Number">{{ number_format($totalDistance, 2, '.', '') }}</Data></Cell>
    <Cell ss:StyleID="TotalCurrency"><Data ss:Type="Number">{{ number_format($overallAvg, 2, '.', '') }}</Data></Cell>
    <Cell ss:StyleID="TotalCurrency"><Data ss:Type="Number">{{ number_format($grandTotalCost, 2, '.', '') }}</Data></Cell>
    <Cell ss:StyleID="TotalCurrency"><Data ss:Type="Number">{{ number_format($overallAvgPerKm ?? 0, 2, '.', '') }}</Data></Cell>
   </Row>

  </Table>
 </Worksheet>
</Workbook>
