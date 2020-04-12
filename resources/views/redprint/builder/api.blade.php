@extends(config('redprint.redprint-layout', 'redprintUnity::page'))

@section('title') API Tester @stop

@section('page_title') API Tester @stop
@section('page_subtitle') Test API @stop
@section('page_icon') <i class="icon-power"></i> @stop

@section('css')
@parent
    <link rel="stylesheet" type="text/css" href="/vendor/redprint/css/redprint.css">
    <link rel="stylesheet" type="text/css" href="/vendor/redprint/vendor/animate.css/animate.css">
    <link rel="stylesheet" href="/vendor/redprint/vendor/element-ui/2.5.4/index.css">
    <style type="text/css">
        pre {
            white-space: pre-wrap;       /* Since CSS 2.1 */
            white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
            white-space: -pre-wrap;      /* Opera 4-6 */
            white-space: -o-pre-wrap;    /* Opera 7 */
            word-wrap: break-word;       /* Internet Explorer 5.5+ */
        }

        .response {
          background: #E8E9E8;
          padding: 20px;
        }

    </style>
@stop


@section('content')

    <div class="card" id="app">

        <div class="card-body">
          <div class="row">
            <div class="col-md-7">

            <form @submit.prevent="postForm" method="POST" >

                <div class="form-group">
                    <div class="row">
                        <div class="col-md-9">
                            <label>Endpoint</label>
                            <input type="text" v-model="endpoint" value="" placeholder="/api/v1/" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Method</label>
                            <select v-model="method" class="form-control">
                                <option value="get" selected="selected">GET</option>
                                <option value="post">POST</option>
                            </select>
                        </div>
                    </div>
                </div>


                <hr />
                <h3>Headers</h3>
                <hr />

                <table class="table table-bordered">
                    <thead class="table-header-color">
                        <tr>
                            <td>Name</td>
                            <td>Value</td>
                        </tr>
                    </thead>

                    <tbody>
                        <tr 
                            is="httpheader"
                            v-for="httpheader in httpheaders" 
                            :id="httpheader.id"
                            :httpheader="httpheader"
                            :add="addHeader"
                            :remove="removeHeader"
                        ></tr>
                    </tbody>

                </table>


                <hr />
                <h3>Params</h3>
                <hr />

                <table class="table table-bordered">
                    <thead class="table-header-color">
                        <tr>
                            <td>Name</td>
                            <td>Value</td>
                        </tr>
                    </thead>

                    <tbody>
                        <tr 
                            is="parameter"
                            v-for="parameter in parameters" 
                            :id="parameter.id"
                            :parameter="parameter"
                            :add="addParameter"
                            :remove="removeParameter"
                        ></tr>
                    </tbody>

                </table>

                <div class="panel-footer">
                    <el-button 
                      type="primary"
                      plain 
                      icon="el-icon-check" 
                      :loading="submitted" 
                      @click.prevent="postForm"
                    >Make Request</el-button>
                </div>

            </form>
        </div>
        <div class="col-md-5">

          <div class="response">
            <h3>Headers</h3>
            <pre>@{{ formattedHeaders }}</pre>
            <h3>Data</h3>
            <pre>@{{ formattedParameters }}</pre>

            <h3>Response</h3>
            <hr />
            <div v-if="responseData.hasOwnProperty('response') && responseData.response.status" >
                <p v-if="responseData.status === 200" class="text text-success">@{{ responseData.status + ': ' + responseData.statusText }}</p>
                <p v-else class="text text-danger">@{{ responseData.response.status + ': ' + responseData.response.statusText + ' [' + responseData.response.data.exception + ': ' + responseData.response.data.message + ']' }}</p>
            </div>

            <pre>@{{ responseData }}</pre>

          </div>
        </div>


      </div>
    </div>
  </div>

  <template id="parameter">
    <tr>
      <td>
        <input type="text" v-model="parameter.name" class="form-control">
      </td>
      <td>
        <input type="text" v-model="parameter.value" class="form-control">
      </td>

      <td>
          <el-button type="danger" plain @click.prevent="remove(id)" v-if="id !== 1" icon="el-icon-remove"></el-button>
          <el-button type="primary" plain @click.prevent="add()" icon="el-icon-circle-plus" v-if="id === 1"></el-button>
      </td>
    </tr>
  </template>


  <template id="httpheader">
    <tr>
      <td>
        <input type="text" v-model="httpheader.name" class="form-control">
      </td>
      <td>
        <input type="text" v-model="httpheader.value" class="form-control">
      </td>

      <td>
          <el-button type="danger" plain @click.prevent="remove(id)" v-if="id !== 1" icon="el-icon-remove"></el-button>
          <el-button type="primary" plain @click.prevent="add()" icon="el-icon-circle-plus" v-if="id === 1"></el-button>
      </td>
    </tr>
  </template>

@stop


@section('head-js')
    @parent
    <script src="/vendor/redprint/vendor/vue/2.6.4/vue.min.js"></script>
    <script src="/vendor/redprint/vendor/axios/0.18.0/axios.min.js"></script>
    <script src="/vendor/redprint/vendor/element-ui/2.5.4/index.js"></script>
@stop

@section('post-js')
    @parent
    <script>
      var token = document.head.querySelector('meta[name="csrf-token"]');
      axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
      axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

      Vue.component('parameter', {
        template: '#parameter',
        props: ['id', 'parameter', 'add', 'remove'],
        data: function () {
            return {}
        }
      })

      Vue.component('httpheader', {
        template: '#httpheader',
        props: ['id', 'httpheader', 'add', 'remove'],
        data: function () {
            return {}
        }
      })

      var app = new Vue({
        el: '#app',
        data: {
          parameters: [
            { id: 1, name: '', value: '' }
          ],
          httpheaders: [
            { id: 1, name: '', value: '' }
          ],
          endpoint: '/api/v1',
          method: 'get',
          submitted: false,
          responseData: 'waiting for response...'
        },
        computed: {
            formattedParameters: function () {
                var data = {}
                for (var i = 0; i < this.parameters.length; i++) {
                    if (this.parameters[i].name) {
                        data[this.parameters[i].name] = this.parameters[i].value
                    }
                }
                return data
            },
            formattedHeaders: function () {
                var data = {}
                for (var i = 0; i < this.httpheaders.length; i++) {
                    if (this.httpheaders[i].name) {
                        data[this.httpheaders[i].name] = this.httpheaders[i].value
                    }
                }
                return data
            }
        },
        methods:{
          addParameter: function () {
            var newInputId = 1
            for (var i = 0; i < this.parameters.length; i++) {
                newInputId = this.parameters[i].id + 1
            }
            this.parameters.push({ id: newInputId, name: '', value: '' })
          },
          removeParameter: function (id) {
           var index = this.parameters.findIndex(function (parameter) {
                return parameter.id === id
           })
           this.parameters.splice(index, 1)
          },
          addHeader: function () {
            var newInputId = 1
            for (var i = 0; i < this.httpheaders.length; i++) {
              newInputId = this.httpheaders[i].id + 1
            }
            this.httpheaders.push({ id: newInputId, name: '', value: '' })
          },
          removeHeader: function (id) {
           var index = this.httpheaders.findIndex(function (httpheader) {
                return httpheader.id === id
           })
           this.httpheaders.splice(index, 1)
          },
          postForm: function () {
            var self = this
            self.submitted = true
            self.responseData = 'waiting for response...'
            var headerdata = self.formattedHeaders
            var paramdata = self.formattedParameters
            axios({
                          method: self.method,
                          url: self.endpoint,
                          data: paramdata,
                          headers: headerdata
                        }).then(function (response) {
              self.submitted = false
              self.responseData = response
            })
            .catch(function (error) {
                self.responseData = error
              self.submitted = false
            });
          }
        }
      });
    </script>
@stop