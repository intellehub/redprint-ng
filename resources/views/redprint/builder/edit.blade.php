@extends(config('redprint.redprint-layout', 'redprintUnity::page'))

@section('title') Builder @stop

@section('page_title') Redprint Builder @stop
@section('page_subtitle') EDIT CRUD Migration @stop
@section('page_icon') <i class="icon-power"></i> @stop

@section('css')
@parent
    <link rel="stylesheet" type="text/css" href="/vendor/redprint/css/redprint.css">
    <link rel="stylesheet" type="text/css" href="/vendor/redprint/vendor/animate.css/animate.css">
    <link rel="stylesheet" href="/vendor/redprint/vendor/element-ui/2.5.4/index.css">
    <style type="text/css">
        span.text-small {
            font-size: 10px;
        }
    </style>
@stop


@section('content')

    <div id="app">
    

        <el-dialog
          :visible.sync="progressDialogVisible"
          width="60%"
          center
          :append-to-body="false"
          :lock-scroll="true"
          :close-on-click-modal="false"
          :close-on-press-escape="false"
          :show-close="success === true || error === true"
          :before-close="closeProgressDialog"
        >
        <center>
            <el-progress type="circle" :percentage="progressPercentage" :color="progressStatus"></el-progress>
        </center>
        <nav>
            <ul id="progress">
                <li
                    v-if="error === false"
                    style="display: none;"
                    class="animated"
                >Optimizing Files...</li>
                <li
                    v-if="error === false"
                    style="display: none;"
                    class="animated"
                >Running PHP Sniffer... Please wait <i class="fa fa-circle-notch fa-spin fa-fw"></i></li>
                <li
                    v-if="success === true"
                    class="animated"
                ><i class="fa fa-fw fa-check-circle"></i>Done!</li>
                <li
                    v-if="success === true"
                    class="animated"
                ><i class="fa fa-fw fa-refresh"></i>Reloading in 3 seconds...</li>
                <li
                    v-if="error === true"
                    class="animated no-background"
                >
                    <ul>
                        <li
                            v-if="error === true"
                        ><code>Error!</code></li>
                        <li
                            v-for="err in errors"
                            class="error"
                            v-if="err[0]"
                            v-html="err[0]"
                        ></li>
                    </ul>
                </li>
            </ul>
        </nav>
          <span slot="footer" class="dialog-footer">
            <el-button v-if="success === true || error === true" @click.prevent="closeProgressDialog">Close</el-button>
          </span>
        </el-dialog>



        <div class="card">

            <div class="card-body">
                
                <form @submit.prevent="postForm" method="POST" >

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4">
                                <label>Model Name</label>
                                <input type="text" v-model="model" value="" placeholder="Model" class="form-control" @blur="optimizeModelName" :disabled="true">
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Enable Softdeletes?</label><br />
                                    <el-checkbox v-model="softdeletes" :disabled="true" label="Softdeletes" border></el-checkbox>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Generate API Code</label><br />
                                    <el-checkbox v-model="api_code" label="API Code" border :disabled="true"></el-checkbox>
                                </div>
                            </div>


                        </div>
                    </div>

                    <hr />
                    <h5>Choose Table contents</h5>
                    <hr />

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-header-color">
                                <tr class="d-flex">
                                    <td class="col-2">Data Type</td>
                                    <td class="col-2">Field Name</td>
                                    <td class="col-4">Options</td>
                                    <td class="col-2">Default Value</td>
                                    <td class="col-2">&nbsp;</td>
                                </tr>
                            </thead>

                            <tbody>
                                <tr 
                                    is="migration"
                                    v-for="migration in migrations" 
                                    :id="migration.id"
                                    :migration="migration"
                                    :add="addInput"
                                    :remove="removeInput"
                                    :old="true"
                                ></tr>

                                <tr 
                                    is="migration"
                                    v-for="migration in migrations_new" 
                                    :id="migration.id"
                                    :migration="migration"
                                    :add="addInput"
                                    :remove="removeInput"
                                    :old="false"
                                ></tr>

                            </tbody>

                        </table>
                    </div>
                    <div class="panel-footer">
                        <el-button type="primary" plain icon="el-icon-check" :loading="submitted" @click.prevent="postForm">Build</el-button>
                    </div>

                </form>
            </div>
        </div>
        <small class="float-right">Redprint v{{ getRedprintVersion() }}</small>
    </div>

    <template id="migration">
        <tr class="d-flex">
            <td class="col-2">
              <el-select v-model="migration.data_type" filterable placeholder="Select" :disabled="id === 1 || old === true">
                <el-option
                  v-for="type in dataTypes"
                  :key="type"
                  :label="type"
                  :value="type">
                </el-option>
              </el-select>
            </td>
            <td  class="col-2">
                <input type="text" v-model="migration.field_name" class="form-control" :disabled="id === 1 || old === true">
            </td>

            <td  class="col-4">
                <table class="table table-bordered">
                    
                    <thead>
                        <tr>
                            <th>
                                <el-tooltip class="item" effect="dark" content="This field will be optional if ticked." placement="top">
                                    <span class="text-small">Null</span>
                                </el-tooltip>
                            </th>
                            <th>
                                <el-tooltip class="item" effect="dark" content="This field will be shown in the form if ticked." placement="top">
                                    <span class="text-small">In Form</span>
                                </el-tooltip>
                            </th>
                            <th>
                                <el-tooltip class="item" effect="dark" content="This field will be indexed for faster searching if ticked." placement="top">
                                    <span class="text-small">Index</span>
                                </el-tooltip>
                            </th>
                            <th>
                                <el-tooltip class="item" effect="dark" content="This field will be shown in index if ticked." placement="top">
                                    <span class="text-small">Show In Index</span>
                                </el-tooltip>
                            </th>
                        </tr>
                    </thead>

                    <tr>
                        <td>
                            <el-checkbox v-model="migration.nullable" :disabled="id === 1 || old === true"></el-checkbox>
                        </td>

                        <td>
                            <el-checkbox :disabled="!migration.nullable" v-model="migration.in_form" :disabled="id === 1 || old === true"></el-checkbox>
                        </td>

                        <td>
                            <el-checkbox v-model="migration.index" :disabled="id === 1  || old === true"></el-checkbox>
                        </td>

                        <td>
                            <el-checkbox v-model="migration.show_index" :disabled="old === true"></el-checkbox>
                        </td>
                    </tr>

                    <thead>
                        <tr>
                            <th>
                                <el-tooltip class="item" effect="dark" content="This field will be searchable if ticked." placement="top">
                                    <span class="text-small">Can Search</span>
                                </el-tooltip>
                            </th>
                            <th>
                                <el-tooltip class="item" effect="dark" content="This field will has to be unique when creating new item if ticked." placement="top">
                                    <span class="text-small">Unique</span>
                                </el-tooltip>
                            </th>
                            <th>
                                <el-tooltip class="item" effect="dark" content="This field will be treated as file and system will generate and uploader for it in the form if ticked." placement="top">

                                    <span class="text-small">Is File</span>
                                </el-tooltip>
                            </th>
                            <th>
                                <el-tooltip class="item" effect="dark" content="Type of file to be uploaded." placement="top">
                                    <span class="text-small">File Type</span>
                                </el-tooltip>
                            </th>
                        </tr>
                    </thead>

                    <tr>
                        <td>
                            <el-checkbox v-model="migration.can_search" :disabled="old === true"></el-checkbox>
                        </td>

                        <td>
                            <el-checkbox v-model="migration.unique" :disabled="id === 1 || old === true"></el-checkbox>
                        </td>

                        <td>
                            <el-checkbox v-model="migration.is_file" :disabled="id === 1 || old === true"></el-checkbox>
                        </td>

                        <td>
                            <select :disabled="!migration.is_file || id===1 || old === true" v-model="migration.file_type" class="form-control">
                                <option value="image">Image</option>
                                <option value="file">File</option>
                            </select>
                        </td>                 
                    </tr>

                    <tr v-if="migration.in_form">
                        <td colspan="4" style="text-align: center;">

                            <el-popover
                              placement="top"
                              title="View Customization"
                              width="400"
                              trigger="click"
                            >
                              <div class="card">
                                  <div class="card-body">
                                      <div class="form-control">
                                          col-xs: 

                                            <el-slider
                                              v-model="migration.col_xs"
                                              :min="1"
                                              :max="12"
                                              :step="1"
                                              :disabled="old === true"
                                              show-stops>
                                            </el-slider>


                                      </div>

                                      <div class="form-control">
                                          col-md: 

                                            <el-slider
                                              v-model="migration.col_md"
                                              :min="1"
                                              :max="12"
                                              :step="1"
                                              :disabled="old === true"
                                              show-stops>
                                            </el-slider>


                                      </div>


                                      <div class="form-control">
                                          col-lg: 

                                            <el-slider
                                              v-model="migration.col_lg"
                                              :min="1"
                                              :max="12"
                                              :step="1"
                                              :disabled="old === true"
                                              show-stops>
                                            </el-slider>


                                      </div>


                                  </div>
                              </div>
                              <el-button slot="reference" type="info" plain icon="el-icon-star-off" :disabled="old === true">View</el-button>
                            </el-popover>
                        
                        </td>
                    </tr>

                </table>
            </td>

            <td class="col-2">
                <input type="text" v-model="migration.default" :disabled="old === true" class="form-control">
            </td>

            <td  class="col-2">
                <el-button type="default" @click.prevent="" icon="el-icon-remove" v-if="id === 1 && old !== true"></el-button>
                <el-button type="danger" plain @click.prevent="remove(id)" icon="el-icon-remove" v-if="id !== 1 && old !== true"></el-button>
                <el-button type="primary" plain @click.prevent="add()" icon="el-icon-circle-plus"></el-button>
            </td>
        </tr>
    </template>

@stop

@section('head-js')
    @parent
    <script src="/vendor/redprint/vendor/modernizr/modernizr.custom.js"></script>
    <script src="/vendor/redprint/vendor/vue/2.6.4/vue.js"></script>
    <script src="/vendor/redprint/vendor/axios/0.18.0/axios.min.js"></script>
    <script src="/vendor/redprint/vendor/element-ui/2.5.4/index.js"></script>
@stop

@section('post-js')
@parent
    <script src="/vendor/redprint/vendor/pluralize/pluralize.js"></script>
    <script>
        var token = document.head.querySelector('meta[name="csrf-token"]');
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

        Vue.component('migration', {
            template: '#migration',
            props: ['id', 'migration', 'add', 'remove', 'old'],
            data: function () {
                return {
                    dataTypes: {!! json_encode($dataTypes) !!},
                }
            },
            mounted: function () {

            }
        })

        var app = new Vue({
            el: '#app',
            data: {
                crud_id: '',
                migrations_new: [
                ],
                migrations: [
                ],
                model: '',
                softdeletes: false,
                api_code: false,
                submitted: false,
                success: false,
                error: false,
                resultUrl: '',
                errors: {},
                progressDialogVisible: false,
                progressPercentage: 0,
                progressStatus: "orange",
                increaseProgress: '',
            },
            computed: {

            },
            created: function () {
                this.crud_id = {!! json_encode($crud_id) !!}
                this.model = {!! json_encode($model) !!}
                this.migrations = {!! json_encode($migrations) !!}
                this.api_code = {!! json_encode($api_code) !!}
                this.softdeletes = {!! json_encode($softdeletes) !!}
            },
            mounted: function () {
            },
            methods:{
                addInput: function () {
                    var newInputId = this.migrations.length + 1
                    for (var i = 0; i < this.migrations_new.length; i++) {
                        newInputId = this.migrations_new[i].id + 1
                    }
                    this.migrations_new.push({ id: newInputId, data_type: 'string', field_name: '', nullable: 0, in_form: true,index: false, show_index: true, can_search: false, unique: false, is_file: false, file_type: 'image', default: null, col_xs: 12, col_md: 12, col_lg: 12 })
                },
                removeInput: function (id) {
                   var index = this.migrations_new.findIndex(function (migration) {
                        return migration.id === id
                   })
                   this.migrations_new.splice(index, 1)
                },
                setProgress: function () {
                    var self = this

                    self.increaseProgress = setInterval(function () {
                        if (self.progressPercentage >= 98) {
                            return true
                        } else {
                            self.progressPercentage = self.progressPercentage + 3
                        }
                    }, 300)

                    $("#progress li").each(function(i) {
                        $(this).delay(1000 * i)
                            .addClass('slideInUp')
                            .fadeIn(3000).delay(1000).fadeIn(function () {
                                if (self.success === true) {
                                    clearInterval(self.increaseProgress)
                                    self.progressPercentage = 100
                                    self.progressStatus = "green"
                                    window.location.replace(self.resultUrl)
                                    return true
                                } else {
                                    clearInterval(self.increaseProgress)
                                    self.progressStatus = "red"
                                    return false
                                }
                                
                            })
                    })
                     
                },
                closeProgressDialog: function (done) {
                    this.resetProgress()
                    this.progressDialogVisible = false
                },
                resetProgress: function () {

                    this.progressPercentage = 0
                    this.progressStatus = "orange"

                    // Remove errors
                    $("#progress li.error").each(function(i) {
                        $(this).remove()
                    })
                    $("#progress li").each(function(i) {
                        $(this).removeClass('slideInUp').hide()
                    })
                },
                pascalCase: function (str) {
                    var camel = str.replace(/_\w/g, (m) => m[1].toUpperCase())
                    return camel.charAt(0).toUpperCase() + camel.slice(1)
                },
                optimizeModelName: function () {
                    if (this.model) {
                        // Make it pascal case
                        this.model = this.pascalCase(this.model)
                        this.model = this.model.replace(/\s/g, '')
                        // Pluralize
                        this.model = pluralize(this.model, 1)
                    }
                },
                postForm: function () {
                    var self = this
                    self.submitted = true
                    self.progressDialogVisible = true
                    self.setProgress()
                    self.error = false
                    self.success = false
                    self.errors = {}
                    
                    var route = {!! json_encode(route('redprint.builder.postEdit')) !!}
                    var postData = {
                        id: this.crud_id,
                        old_migrations: this.migrations,
                        migration: this.migrations_new,
                        model: this.model,
                        softdeletes: this.softdeletes,
                        api_code: this.api_code 
                    }
                    axios.post(route, postData)
                      .then(function (response) {
                        self.submitted = false
                        self.resultUrl = response.data.route
                        self.setProgress()
                        setTimeout(function(){
                            self.success = true 
                        }, 3000)
                      })
                      .catch(function (error) {
                        self.setProgress()
                        console.log(JSON.stringify(error.response.data))
                        self.error = true
                        self.success = false
                        self.errors = error.response.status == 422 ? error.response.data.errors : {'message': error.response.message}
                        self.submitted = false
                      });
                }
            }
        });
    </script>
@stop