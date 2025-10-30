# default deploy_config_path is 'config/deploy.rb'
set :deploy_config_path, File.expand_path('capistrano/deploy.rb')
# default stage_config_path is 'config/deploy'
set :stage_config_path, 'capistrano/deploy'

# Load DSL and set up stages
require "capistrano/setup"

# Include default deployment tasks
require "capistrano/deploy"
require "capistrano/scm/git"

install_plugin Capistrano::SCM::Git

# Include tasks from other gems included in your Gemfile
#
# For documentation on these, see for example:
#
#   https://github.com/capistrano/rvm
#   https://github.com/capistrano/rbenv
#   https://github.com/capistrano/chruby
#   https://github.com/capistrano/bundler
#   https://github.com/capistrano/rails
#   https://github.com/capistrano/passenger
#
# require 'capistrano/rvm'
# require 'capistrano/rbenv'
# require 'capistrano/chruby'
# require 'capistrano/bundler'
# require 'capistrano/rails/assets'
# require 'capistrano/rails/migrations'
# require 'capistrano/passenger'

# Load custom tasks from `lib/capistrano/tasks` if you have any defined
Dir.glob("capistrano/tasks/*.rb").each { |r| import r }
task :fix_timestamp do
  env.instance_variable_set(:@timestamp, Time.now)
end
after :"load:defaults", :fix_timestamp
