@extends(config('redprint.redprint-layout', 'redprintUnity::page'))

@section('title') Make Tools @stop

@section('page_title') Redprint Make Tools @stop
@section('page_subtitle') Run Laravel Commands @stop
@section('page_icon') <i class="icon-center_focus_strong"></i> @stop

@section('css')
@parent
    <link rel="stylesheet" type="text/css" href="/vendor/redprint/css/redprint.css">
    <link rel="stylesheet" type="text/css" href="/vendor/redprint/vendor/animate.css/animate.css">
    <style type="text/css">
      .btn-active {
        color: #fff !important;
      }
      .terminal-window {
        height: 550px;
        width: 100%;
        margin-top: 10px;
      }
      button {
        margin-bottom: 5px;
      }
      .btn {
        font-size: 0.8rem !important;
        white-space: normal;
      }
      .cockpit {
        background: #E8E9E8;
        padding: 20px;
      }
      .history span {
        white-space: pre !important;
      }
    </style>
@stop

@section('content')
    
    <div id="app">

        <div class="card">

            <div class="card-body">

                <div class="row">
                    <div class="col-md-4">
                        
                        
                        <form @submit.prevent="postForm" method="POST" class="cockpit">

                            <div class="form-group">

                                <div class="row">
                                    <div class="col-md-12">
                                      <h5 style="padding: 20px 0px;">Make Commands</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <button :class="boxClass('model')" @click.prevent="generateMakeCommand('model')">
                                            <i class="fa fa-cube mr-2"></i>Model
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('view')" @click.prevent="generateMakeCommand('view')">
                                            <i class="fa fa-file-alt mr-2"></i>View
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('controller')" @click.prevent="generateMakeCommand('controller')">
                                            <i class="fa fa-cogs mr-2"></i>Controller
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('migration')" @click.prevent="generateMakeCommand('migration')">
                                            <i class="fa fa-database mr-2"></i>Migration
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('command')" @click.prevent="generateMakeCommand('command')">
                                            <i class="fa fa-terminal mr-2"></i>Command
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('middleware')" @click.prevent="generateMakeCommand('middleware')">
                                            <i class="fa fa-ban mr-2"></i>Middleware
                                        </button>
                                    </div>
                                </div>

                                <div class="row" style="margin-top: 20px;">

                                    <div class="col-md-6">
                                        <button :class="boxClass('mail')" @click.prevent="generateMakeCommand('mail')">
                                            <i class="fa fa-envelope mr-2"></i>Mail
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('provider')" @click.prevent="generateMakeCommand('provider')">
                                            <i class="fa fa-tasks mr-2"></i>Provider
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('event')" @click.prevent="generateMakeCommand('event')">
                                            <i class="fa fa-bullhorn mr-2"></i>Event
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('resource')" @click.prevent="generateMakeCommand('resource')">
                                            <i class="fa fa-paperclip mr-2"></i>Resource
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('request')" @click.prevent="generateMakeCommand('request')">
                                            <i class="fa fa-paper-plane mr-2"></i>Request
                                        </button>
                                    </div>

                                    <div class="col-md-6">
                                        <button :class="boxClass('seeder')" @click.prevent="generateMakeCommand('seeder')">
                                            <i class="fa fa-info mr-2"></i>Seeder
                                        </button>
                                    </div>

                                </div>

                                <hr />
                                <div class="row">
                                  <div class="col-md-12">
                                    <h5 style="padding: 20px 0px;">System Commands</h5>
                                  </div>

                                  <div class="col-md-6">
                                      <button :class="boxClass('update')" @click.prevent="composerCommand('update')">
                                          <i class="fa fa-terminal mr-2"></i>composer update
                                      </button>
                                  </div>
                                  <div class="col-md-6">
                                      <button :class="boxClass('install')" @click.prevent="composerCommand('install')">
                                          <i class="fa fa-terminal mr-2"></i>composer install
                                      </button>
                                  </div>
                                  <div class="col-md-6">
                                      <button :class="boxClass('dump-autoload')" @click.prevent="composerCommand('dump-autoload')">
                                          <i class="fa fa-terminal mr-2"></i>composer dump-autoload
                                      </button>
                                  </div>
                                </div>



                                <hr />
                                <div class="row">
                                  <div class="col-md-12">
                                    <h5 style="padding: 20px 0px;">Code Sniffer</h5>
                                  </div>

                                  <div class="col-md-6">
                                      <button :class="boxClass('phpcs')" @click.prevent="snifferCommand('phpcs')">
                                          <i class="fa fa-terminal mr-2"></i>phpcs
                                      </button>
                                  </div>
                                  <div class="col-md-6">
                                      <button :class="boxClass('phpcbf')" @click.prevent="snifferCommand('phpcbf')">
                                          <i class="fa fa-terminal mr-2"></i>phpcbf
                                      </button>
                                  </div>
                                </div>

                                <div class="row">
                                  <div class="col-md-12">
                                    <h5 style="padding: 20px 0px;">Artisan Commands</h5>
                                  </div>
                                  <div class="col-md-6">
                                      <button :class="boxClass('migrate')" @click.prevent="artisanCommand('migrate')">
                                          <i class="fa fa-magic mr-2"></i>Migrate
                                      </button>
                                  </div>
                                  <div class="col-md-6">
                                      <button :class="boxClass('migrate:rollback')" @click.prevent="artisanCommand('migrate:rollback')">
                                          <i class="fa fa-magic mr-2"></i>Migrate Rollback
                                      </button>
                                  </div>
                                  <div class="col-md-6">
                                      <button :class="boxClass('clear-compiled')" @click.prevent="artisanCommand('clear-compiled')">
                                          <i class="fa fa-magic mr-2"></i>Clear Compiled
                                      </button>
                                  </div>
                                  <div class="col-md-6">
                                      <button :class="boxClass('cache:clear')" @click.prevent="artisanCommand('cache:clear')">
                                          <i class="fa fa-magic mr-2"></i>Clear Cache
                                      </button>
                                  </div>

                                  <div class="col-md-6">
                                      <button :class="boxClass('cache:clear')" @click.prevent="artisanCommand('cache:clear')">
                                          <i class="fa fa-magic mr-2"></i>Clear Cache
                                      </button>
                                  </div>

                                  <div class="col-md-6">
                                      <button :class="boxClass('cache:clear')" @click.prevent="artisanCommand('route:list')">
                                          <i class="fa fa-magic mr-2"></i>Route List
                                      </button>
                                  </div>


                                </div>

                            </div>

                            <div class="panel-footer">
                            </div>

                        </form>


                    </div>

                    <div class="col-md-8">

                        <div class="row">    
                            <div class="col-md-12" style="margin-bottom: 10px;">
                                <div class="row">
                                    <div class="col-md-5">
                                        <label>Name</label>
                                        <input type="text" v-model="commandParam" value="" :placeholder="capitalizeFirstLetter(command) + ' Name'" class="form-control" @blur="optimizeName" :disabled="!showCommandPalette" id="commandInput">
                                    </div>

                                    <div class="col-md-5">
                                        <label>Option</label>
                                        <input type="text" v-model="commandOption" value="" placeholder="option" class="form-control" @blur="optimizeName" :disabled="!showCommandPalette">
                                    </div>
                                </div>
                            </div>


                            <hr />
                            <div class="col-md-12">
                                <button class="btn btn-success btn-md" @click.prevent="postForm">
                                    <i v-if="submitted" class="fa fa-circle-notch fa-spin fa-fw"></i>
                                    <span>Run</span>
                                </button>
                                <button class="btn btn-info btn-md" @click.prevent="clearConsole">
                                    <span>Clear Console</span>
                                </button>
                            </div>
                        </div>
                        <div class="spacer-20"></div>

                        <div class="terminal-window">
                          <header>
                            <div class="button green"></div>
                            <div class="button yellow"></div>
                            <div class="button red"></div>

                            <button 
                              class="btn btn-info btn-xs float-right"
                              @click.prevent="changeFontSize(true)"
                            >
                              <i class="fa fa-search-plus"></i>
                            </button>
                            <button 
                              class="btn btn-primary btn-xs float-right"
                              @click.prevent="changeFontSize(false)"
                            >
                              <i class="fa fa-search-minus"></i>
                            </button>
                          </header>
                          <section class="terminal">
                            <div class="history" :style="terminalCss">
                                <template v-for="(line, index) in lines" :key="index">
                                    <span v-if="!line.response">$ @{{ line.text }}</span>
                                    <span v-if="working && index === lines.length - 1">
                                      <i class="fa fa-circle-notch fa-spin fa-fw"></i>
                                    </span>
                                    <span :class="line.green === true ? 'green' : 'red'" v-else>@{{ line.text }}</span>
                                </template>
                            </div>
                            $&nbsp;<span class="prompt" v-html="commandText"></span><span class="typed-cursor">|</span>
                            
                          </section>
                        </div>

                    </div>
                </div>
            

            </div>
        </div>
    </div>


@stop

@section('head-js')
    @parent
    <script src="/vendor/redprint/vendor/modernizr/modernizr.custom.js"></script>
    <script src="/vendor/redprint/vendor/vue/2.6.4/vue.min.js"></script>
    <script src="/vendor/redprint/vendor/axios/0.18.0/axios.min.js"></script>
@stop

@section('post-js')
@parent
    <script src="/vendor/redprint/vendor/classie/classie.js"></script>
    <script src="/vendor/redprint/vendor/pluralize/pluralize.js"></script>
    <script>
        var token = document.head.querySelector('meta[name="csrf-token"]');
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

        var app = new Vue({
            el: '#app',
            data: {
                fontSize: 14,
                command: '',
                type: '',
                commandParam: '',
                commandOption: '',
                working: false,
                softdeletes: false,
                submitted: false,
                success: false,
                error: false,
                lines: [],
                errors: {}
            },
            computed: {
                commandText: function () {
                    var option = this.commandOption !== '' ? ' --' + this.commandOption : ''
                    if (this.type === 'make') {
                      return 'php artisan make:' + this.command + ' ' + this.commandParam + option
                    }
                    if (this.type === 'artisan') {
                      return 'php artisan ' + this.command
                    }
                    if (this.type === 'composer') {
                      return 'composer ' + this.command
                    }
                    if (this.type === 'sniffer') {
                      return 'vendor/bin/' + this.command + ' /app'
                    }
                    return this.command
                },
                showCommandPalette: function () {
                  return this.command !== '' && this.type === 'make'
                },
                terminalCss: function () {
                  return 'font-size: ' + this.fontSize + 'px;'
                }
            },
            mounted: function () {
            },
            methods:{
                changeFontSize: function (increase = true) {
                  if (increase === true) {
                    this.fontSize++
                  } else {
                    this.fontSize--
                  }
                  console.log(this.fontSize)
                },
                capitalizeFirstLetter: function (string) {
                    return string.charAt(0).toUpperCase() + string.slice(1)
                },
                boxClass: function (makeName) {
                    var commonPrefix = 'btn btn-outline-primary btn-block'
                    return this.command === makeName ?  commonPrefix + ' btn-active' : commonPrefix
                },
                generateMakeCommand: function (makeName) {
                    this.fontSize = 14
                    this.type = 'make'
                    // Focus text input
                    this.$nextTick(function () {
                      document.getElementById('commandInput').focus()
                    })
                    this.command = makeName
                },
                artisanCommand: function (command) {
                    this.fontSize = 14
                    this.type = 'artisan'
                    this.command = command
                },
                composerCommand: function (command) {
                  this.fontSize = 14
                    this.type = 'composer'
                    this.command = command
                },
                snifferCommand: function (command) {
                  this.fontSize = 12
                  this.type = 'sniffer'
                  this.command = command
                },
                runCommand: function (command) {
                    this.fontSize = 14
                    this.type = 'system'
                    this.command = command
                },
                pascalCase: function (str) {
                    return str.replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
                },
                optimizeName: function () {
                    if (this.commandParam) {
                        if (this.command === 'migration' || this.command === 'seeder' || this.command === 'view') {
                            // Make it lower case
                            this.commandParam = this.commandParam.toLowerCase()
                            this.commandParam = this.commandParam.replace(/\s/g, '_')
                        } else {
                            // Make it pascal case
                            this.commandParam = this.pascalCase(this.commandParam)
                            if (this.command === 'model') {
                                // Singularize
                                this.commandParam = pluralize(this.commandParam, 1)
                            }
                            this.commandParam = this.commandParam.replace(/\s/g, '')
                        }
                        
                    }
                },
                addResponse: function (response) {
                    this.lines.push({text: response, green: this.success, response: true })
                },
                clearConsole: function () {
                  this.working = false
                  this.lines = []
                },
                postForm: function () {
                    var self = this
                    self.submitted = true
                    self.error = false
                    self.success = false
                    self.errors = {}
                    self.lines.push({text: self.commandText, green: false, response: false })
                    self.working = true
                    var route = {!! json_encode(route('redprint.make-tools.post')) !!}
                    axios.post(route, { command: this.command, type: this.type, param: this.commandParam, option: this.commandOption })
                      .then(function (response) {
                        self.submitted = false
                        self.success = true
                        self.working = false
                        self.addResponse(response.data.message)
                      })
                      .catch(function (error) {
                        console.log(JSON.stringify(error.response.data))
                        self.error = true
                        self.success = false
                        self.submitted = false
                        self.working = false
                        self.addResponse(error.response.data.message)
                      });
                }
            }
        });

    </script>
@stop