<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="baseUrl" content="<?php echo $this->baseUrl('/'); ?>"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"/>
    <title>British Council :: <?php echo strip_tags($this->title); ?></title>
    <link href="<?php echo APP_ROOT_PATH . '/assets/fonts/proxima-nova-fontfacekit/stylesheet.css';?>" rel="stylesheet"/>
    <link href="<?php echo APP_ROOT_PATH . '/assets/lib/foundation/css/normalize.css';?>" rel="stylesheet"/>
    <link href="<?php echo APP_ROOT_PATH . '/assets/lib/foundation/css/foundation.css';?>" rel="stylesheet"/>
    <link href="<?php echo APP_ROOT_PATH . '/assets/lib/c3/css/c3.css';?>" rel="stylesheet"/>
    <link href="<?php echo APP_ROOT_PATH . '/assets/css/font-awesome.min.css';?>" rel="stylesheet"/>
    <script>
        Function.prototype.bind = Function.prototype.bind || function (thisp) {
            var fn = this;
            return function () {
                return fn.apply(thisp, arguments);
            };
        };
    </script>
    <script type="text/javascript" src="<?php echo APP_ROOT_PATH . '/assets/js/modernizr.min.js';?>"></script>
    <style>

        h1 {
            font-size: 20px;
            color: #0a3542;
            line-height: 1.1;
            text-transform: uppercase;
        }

        .top-information {
            margin-bottom: 12px;
            padding-top: 10px;
        }

        .top-information img {
            position: relative;
            top: -5px;
        }

        .top-information .information{
            color: #888;
            font-size: 18px;
            position: relative;
            bottom: 4px;
        }

        .top-information .information .darker {
            color: #222;
        }

        .score-section {
            padding-top: 6px;
            padding-bottom: 110px;
            border-top: 2px solid #0a3542;
        }

        .border-section {
            padding-top: 6px;
            border-top: 2px solid #0a3542;
        }

        h2 {
            font-size: 16px;
            text-transform: uppercase;
        }

        .rank {
            font-size: 50px;
            position: relative;
            color: #222;
        }

        .rank .ordinal-indicator {
            font-size: 40px;
            position:relative;
            top: -14px;
            padding-right: 20px;
        }

        .rank .denominator {
            font-size: 28px;
        }

        .rank .lighter {
            color: #888;
        }

        .rank .change {
            font-size: 28px;
            position: relative;
            bottom: -20px;
            color: orange;
        }

        .rank .change.positive {
            color: green;
        }

        .rank .change.positive:before {
            content: "+";
            color: green;
        }

        .rank .change.negative {
            color: red;
        }

        .info-section h3 {
            font-size: 16px;
            text-transform: uppercase;
        }

        .info-section p {
            font-size: 14px;
        }

    </style>
</head>
<body class="<?php echo $this->bodyClass; ?>">

<div class="row">
    <div class="small-24 columns">
        <section class="top-information">
            <div class="row">
                <div class="small-6 columns">
                    <h1>Social Media Monitor Report</h1>
                </div>
                <div class="small-12 columns">
                    <div class="information">
                        <div><i class="fa fa-fw <?php echo $this->report->getIcon(); ?>"></i> <?php echo $this->report->getType(); ?>: <span class="darker"><?php echo $this->report->getName(); ?></span></div>
                        <div><i class="fa fa-calendar fa-fw"></i> Date range: <span class="darker"><?php echo $this->report->getDateRange(); ?></span></div>
                    </div>
                </div>
                <div class="small-6 columns">
                    <img src="<?php echo APP_ROOT_PATH . '/assets/img/BritishCouncil_sm.png';?>"/>
                </div>
            </div>
        </section>

        <?php foreach ($this->report->getRanks() as $type => $rank) : ?>
            <section class="score-section border-section">
                <div class="row">
                    <div class="small-6 columns">
                        <h2><?php echo $rank['title']; ?></h2>

                        <div class="rank">
                            <?php $change = $rank['change']; ?>
                            <?php if ($type == 'total') : ?>
                                <?php echo $rank['rank']; ?>/<span class="lighter denominator"><?php echo $rank['denominator']; ?></span><span class="change <?php echo $change['class']; ?>"><?php echo $change['number']; ?></span>
                            <?php else: ?>
                                <?php echo $rank['rank']; ?><span class="ordinal-indicator"><?php echo $rank['ordinal']; ?></span><span class="change <?php echo $change['class']; ?>"><?php echo $change['number']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="small-18 columns">
                        <div class="chart <?php echo $rank['chart_class']; ?>"></div>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>

        <?php echo $this->layout()->content; ?>

        <!--        <div class="row">-->
        <!--            <div class="small-12 columns">-->
        <!--                <section class="border-section info-section">-->
        <!--                    <h2>Additional Info</h2>-->
        <!--                    <h3>Countries: 18</h3>-->
        <!--                    <p>Albania  Armenia  Azerbaijan  Bosnia and Herzegovina  Croatia  Estonia  Georgia  Israel  Kazakhstan  Kosovo  Lithuania  Macedonia, the Former Yugoslav Republic of  Montenegro  Russian Federation  Serbia  Turkey  Ukraine  Uzbekistan</p>-->
        <!--                </section>-->
        <!--            </div>-->
        <!--            <div class="small-12 columns">-->
        <!--                <section class="border-section info-section">-->
        <!--                    <h3>Presences: 32</h3>-->
        <!--                    <p>@alBritish  @amBritish  @azBritish  BritishCouncilAlbania  Brit-ishCouncilArmenia  BritishCouncilAzerbaijan  BritishCouncilBos-niaandHerzegovina  BritishCouncilCroatia  BritishCouncilEstonia  BritishCouncilGeorgia  BritishCouncilIsrael  BritishCouncilKazakhstan  BritishCouncilKosovo  BritishCouncilLithuania  BritishCouncilMacedo-nia  BritishCouncilMontenegro  BritishCouncilRussia  BritishCouncilS-erbia  BritishCouncilTurkey  BritishCouncilUkraine  BritishCouncilUz-bekistan  @eeBritish  @geBritish  @hrBritish  @ilBritish  @ksBritish  @mkBritish  OblaAirTeMesojmeAnglishten  @rsBritish  @ruBritish  @trBritish  @uaBritish</p>-->
        <!--                </section>-->
        <!--            </div>-->
        <!---->
        <!--        </div>-->



    </div>
</div>

<script type="text/javascript" src="<?php echo APP_ROOT_PATH . '/assets/lib/d3/v3/d3.min.js';?>"></script>
<script type="text/javascript" src="<?php echo APP_ROOT_PATH . '/assets/lib/c3/js/c3.js';?>"></script>

<script>
    <?php foreach ($this->report->getCharts() as $chart) : ?>
    c3.generate(<?php echo $chart; ?>);
    <?php endforeach; ?>

</script>

</body>
</html>