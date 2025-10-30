# http://vladigleba.com/blog/2014/04/10/deploying-rails-apps-part-6-writing-capistrano-tasks/
namespace :git do

  desc "Makes sure local git is in sync with remote."
  task :check do
    unless `git rev-parse #{fetch(:branch)}` == `git rev-parse origin/#{fetch(:branch)}`
      puts "WARNING: Repository is not the same as origin/#{fetch(:branch)}"
      puts "Run `git push origin #{fetch(:branch)}` to sync changes."
      exit
    end
  end

end

before :deploy, 'git:check'
