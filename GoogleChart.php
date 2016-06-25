<?php
// Jordan McQueen

// TODO:
// 
//      - each chartClass should have its own PHP class
//          - mkdir charts
//          - PiChart.php, BarChart.php, etc.
//          - each of these will extend GoogleChart.php
//      
//          - constructor will do something along these lines, akin to a driver:
//              - construct things common to ALL types of chart (data, title, etc)
//              - $this->class_instance = $this->kind . "Chart"
//              - return new {$this->class_instance}($this);
//      - adhere to 80 width lines
//      - build out $config options

class GooglePlot
{

	include('config/config.php');	

    private $kind;
    private $dependents;
    private $independent;
    private $dataTable;
    private $codename;
    private $title;
    private $data;
    private $chartClass;
    private $package;
    private $isSharingAxes;
    private $independentType;
    private $dataHeaders;
    private $isControllable;
    private $isIncludingPng;
    private $hasResults;
    private $linkedReport;

    # TODO
    #
    # factor static $releases OUT
    #   - create $this->annotated_dates = $config['annotated_dates'];
    #   - refactor so all references to $releases work. 
    static $releases = $config['annotated_dates'];

    # I wrote this constructor when I was much less experienced...
    # TODO
    #
    # refactor this into better code. 
    public function __construct($args)
    {
        $this->title = $args['title'];
        $this->hasResults = array_key_exists('hasResults', $args) ? $args['hasResults'] : True;
        if ($this->hasResults === True)
        {
            $this->kind = array_key_exists('kind', $args) ? strtolower($args['kind']) : 'line';
            $this->codename = preg_replace('/[\s0-9,\'"\)\(]+/', '', $this->title) . substr(md5(rand()), 0, 7);
            $this->data = $args['data'];
            $this->dataTransformer();
            $this->refreshDataHeaders();
            $this->isControllable = array_key_exists('isControllable', $args) ? $args['isControllable'] : False;
            $args['independent'] = array_key_exists('independent', $args) ? $args['independent'] : '';
            $this->setIndependent($args['independent']);
            $this->isIncludingPng = array_key_exists('isIncludingPng', $args) ? $args['isIncludingPng'] : False;
            $this->linkedReport = array_key_exists('linkedReport', $args) ? $args['linkedReport'] : Null;
            $this->dependents = array_key_exists('dependents', $args) ? $args['dependents'] : $this->buildDependentsGuess();
            $this->chartClass = $this->lookupChartClass();
            $this->package = $this->lookupPackage(); 
            $this->isSharingAxes = array_key_exists('isSharingAxes', $args) ? $args['isSharingAxes'] : True;
            $this->makeJsDataTable();
        }
    }


    private function refreshDataHeaders()
    {
        $this->dataHeaders = [];

        foreach ($this->data[0] as $key => $value)
        {
            $this->dataHeaders[] = $key;
        }
    }


    public function getDataHeaders()
    {
        $this->refreshDataHeaders();
        return $this->dataHeaders;
    }


    public function getIndependent()
    {
        return $this->independent;
    }


    public function getIsControllable()
    {
        return $this->isControllable;
    }


    public function setIsControllable($boolean)
    {
        if (is_bool($boolean) === False) {
            $type = gettype($boolean);
            throw new Exception("setIsControllable of GooglePlot class requires boolean input. $type was given.");
        }
        $this->isControllable = $boolean;
        return $this;
    }


    private function buildDependentsGuess()
    {   
        return array_diff($this->getDataHeaders(), [$this->getIndependent()]);
    }


    public function withPng()
    {
        $this->isIncludingPng = True;
        return $this;
    }

    public function setIndependent($independent)
    {
        switch (True)
        {
            case (is_array($independent)):
                $this->independent = $independent['name'];
                $this->independentType = $independent['type'];
                break;
            case (!empty($independent) && DateTime::createFromFormat('Y-m-d', $this->getData()[0]->$independent) !== FALSE):
                $this->independent = $independent;
                $this->independentType = 'date';
                break;
            case (in_array('date', $this->getDataHeaders()) && empty($independent)):
                $this->independent = 'date';
                $this->independentType = 'date';
                break;
            default:
                $this->independent = $independent;
                $this->independentType = 'string';
                break;
        }
        return $this;
    } 


    public function setKind($kind)
    {
        $this->kind = $kind;
        return $this;
    }


    public function setIsSharingAxes($boolean)
    {
        if (!is_bool($boolean)) {
            $type = gettype($boolean);
            throw new Exception ("setIsSharingAxes() of GooglePlot class requires type Boolean; $type was given.");
        }
        $this->isSharingAxes = $boolean;
        return $this;
    }


    public function getKind()
    {   
        return $this->kind;
    }


    public function getData()
    {
        return $this->data;
    }


    public function with($option) {
        $option = strtolower($option);

        if (!in_array($option, $this->config->supported_options)) {
            throw new Exception("$option is not a supported with() option.");
        }
        
        if (in_array($option, $this->options)) {
            return $this;
        }

        # everything looks good... continuing
        $this->options[] = $option;
        return $this;
    }


    # TODO:
    # Build out the various with_() functions.

    private function with_separate_axes() {
        
    } 

    public function setDependents($dependents)
    {
        $this->dependents = $dependents;
        return $this;
    }


    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    public function getTitle()
    {
        return $this->title;
    }

    public function getDependents()
    {
        return $this->dependents;
    }


    public function getIndependentType()
    {
        return $this->independentType;
    }


    public function addDependent($dependent)
    {
        $this->dependents[] = $dependent;
        return $this;
    }

    #TODO:
    #
    # I thought this was a funny function name at the time,
    # but now that I have this public published,
    # change it to something better.
    private function prepare_independent($value)
    {
        switch ($this->getIndependentType())
        {
            case 'date':
                $value = new DateTime($value);
                $value = $value->modify('+1 day')->format('Y-m-d');
                $value = "new Date('$value')";
                return $value;
                break;
            case 'number':
                return $value;
                break;
            case 'string':
                return "'$value'";
                break;
        }
    }


    private function getDataTable()
    {
        return $this->dataTable;
    }


    private function dataTransformer()
    {
        switch ($this->getKind())
        {
            case 'pie':
                break;
            default:
                break;
        }
    }
    
    #TODO:
    #
    #clean this up.
    private function makeJsDataTable()
    {
        $data_body = "";
        foreach ($this->data as $row)
        {
            if ($this->getIndependentType() == 'date' && array_key_exists($row->{$this->getIndependent()}, GooglePlot::$releases))
            {
                $annotation = "'R'";
                $annotation_text = "'{$this::$releases[$row->{$this->independent}]}'";
            } else {
                $annotation = 'null';
                $annotation_text = "null";
            }
            
            $x = $this->prepare_independent($row->{$this->independent});
            $data_body .= "[$x";
            foreach ($this->dependents as $y)
            {
                $value = $row->{$y};
                if ($value == NULL) {
                    $value = 0;
                }
                $data_body .= ", $value";
            }
            if ($this->independentType == 'date') {
                $data_body .= ", $annotation";
                $data_body .= ", $annotation_text";
            }
            $data_body .= "],\n\t\t\t\t";
        }
        $this->dataTable = $data_body;
    }    


    private function getSpecialOptions()
    {
        $special_options = "";
        switch ($this->kind)
        {
            case 'stacked':
                $special_options .= "isStacked: true,\n";
            default:
                $special_options .= "pointSize: {$this->getPointSize()}";
        }
        return $special_options;
    }


    public function setPointSizeOptions($size=Null)
    {
        if ($size != Null) {
            $this->pointSize = $size;
            return $this;
        }

        switch ($this->kind)
        {
            case 'scatter':
                $this->pointSize = 1;
                break;
            default:
                $this->pointSize = 0;
                break;
        }
        return $this;
    }


    public function getPointSize()
    {
        if (isset($this->pointSize) === False) {
            $this->setPointSizeOptions();
        }
        return $this->pointSize;
    }


    private function getOptions()
    {
        $options = "var options = {
                title: '$this->title',
                height: 400,
                {$this->getAxesOptions()},
                {$this->getSpecialOptions()}
            };";
        return $options;
    }
    

    private function lookupPackage()
    {
        switch ($this->kind)
        {
            case 'table':
                return 'table';
                break;
            default:
                return 'corechart';
                break;
        }
    }


    private function lookupChartClass()
    {
        $class_lookup = [
            'timeseries' => 'LineChart',
            'line' => 'LineChart',
            'column' => 'ColumnChart',
            'combo' => 'ComboChart',
            'pie' => 'PieChart',
            'area' => 'AreaChart',
            'stacked' => 'AreaChart',
            'bar' => 'BarChart',
            'table' => 'Table',
            'scatter' => 'ScatterChart',
            ];
        return $class_lookup[$this->kind];
    }


    private function buildColumns()
    {
        $columns = "";
        $columns .= "data.addColumn('{$this->getIndependentType()}', '{$this->getIndependent()}');";
        foreach ($this->getDependents() as $dependent)
        {
            $columns .= "\n\t\t\t";
            $columns .= "data.addColumn('number', '$dependent');";
        }
        if ($this->independentType == 'date') {
            $columns .= " data.addColumn({type:'string', role:'annotation'});
            data.addColumn({type:'string', role:'annotationText'});";
        }

        return $columns;
    }


    private function getAxesOptions()
    {
        $axes = "vAxes: {\n";
        $series = "\t\t\t\tseries: {\n";
        if ($this->isSharingAxes === False) {
            foreach ($this->dependents as $index => $y)
            {
                $axes .= "\t\t\t\t\t$index: {title: '$y'},\n";
                $series .= "\t\t\t\t\t$index:{ targetAxisIndex: $index},\n";
            }
        } else if ($this->isSharingAxes === True) {
            $axes .= "\t\t\t\t\t0: {title: ''},\n";
            foreach ($this->dependents as $index => $y)
            {
                $series .= "\t\t\t\t\t$index:{ targetAxisIndex: 0},\n";
            }
        }
        $axes .= "\t\t\t\t" . "}," ."\n";
        $series .= "\t\t\t\t" . "}";
        return $axes . $series;
    }


    public function getJavascript()
    {
        switch ($this->getIsControllable())
        {
            case True:
                return $this->buildJsForDashboard();
                break;
            case False:
                return $this->buildJsForChart();
                break;
        }
    }


    private function buildJsExtras()
    {
        if ($this->isIncludingPng === True) {
            return "google.visualization.events.addListener(chart, 'ready', function () {
                 png = chart.getImageURI();
             });";
        }
    }


    private function buildJsForChart()
    {
        $js = "
        <div id='$this->codename' style='border: 0px solid; width:1400px;'></div>
        <script type='text/javascript'>
        google.load('visualization', '1', {packages:['{$this->package}']});
        google.setOnLoadCallback($this->codename);
        function $this->codename() {
            var data = new google.visualization.DataTable()
            {$this->buildColumns()}
            data.addRows(
            [
                {$this->getDataTable()}
            ]);

            {$this->getOptions()}
            var chart = new google.visualization.{$this->chartClass}(document.getElementById('$this->codename'));
            {$this->buildJsExtras()}
            chart.draw(data, options);
        }
        </script>";

        return $js;
    }

    // TODO
    // need to build out method to create a google.visualization.Dashboard object
    //
    // public function buildJsForDashboard()


    public function display()
    {
        if ($this->hasResults === False) {
            echo "No data found to plot with for $this->title.";
            return Null;
        }
        echo $this->getJavascript(); #do I really want this to echo? maybe return is better.
    }
}
